<?php

namespace StoreKeeper\WooCommerce\B2C\Tools;

use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceShopInfo;
use StoreKeeper\WooCommerce\B2C\Core;
use StoreKeeper\WooCommerce\B2C\Exceptions\BaseException;
use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\TestLib\MediaHelper;

class Media
{
    public const CDN_URL_VARIANT_PLACEHOLDER_KEY = '{variant}';
    public const FULL_VARIANT_KEY = 'sk_full';

    public static function fixUrl($url)
    {
        if (StringFunctions::startsWith($url, '/')) {
            $server = StoreKeeperOptions::get(StoreKeeperOptions::API_URL);
            $url = $server.$url;
        }

        if (false !== strpos($url, '?')) {
            $url = explode('?', $url);
            array_pop($url);
            $url = join('?', $url);
        }

        return $url;
    }

    public static function ensureAttachment($original_url)
    {
        if ($attachment = self::getAttachment($original_url)) {
            return $attachment->ID;
        } else {
            return self::createAttachment($original_url);
        }
    }

    /**
     * @return \WP_Post|false;
     */
    public static function getAttachment($original_url)
    {
        $attachments = get_posts(
            [
                'post_type' => 'attachment',
                'posts_per_page' => 1,
                'meta_key' => 'original_url',
                'meta_value' => $original_url,
                'post_status' => ['publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit'],
            ]
        );

        return current($attachments);
    }

    public static function getAttachmentId($original_url): ?int
    {
        $attachments = get_posts(
            [
                'post_type' => 'attachment',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'meta_key' => 'original_url',
                'meta_value' => $original_url,
                'post_status' => ['publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit'],
            ]
        );
        if (!empty($attachments)) {
            return $attachments[0];
        }

        return null;
    }

    public static function getAttachmentIdsByUrls(array $original_urls): array
    {
        if (empty($original_urls)) {
            return [];
        }
        global $wpdb;
        $placeholders = implode(', ', array_fill(0, count($original_urls), '%s'));

        $sql = "
    SELECT p.ID, pm.meta_value
    FROM {$wpdb->posts} p
    INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
    WHERE p.post_type = 'attachment'
    AND pm.meta_key = 'original_url'
    AND pm.meta_value IN ($placeholders)
    AND p.post_status IN ('publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit')
";

        $query = $wpdb->prepare($sql, $original_urls);
        $results = $wpdb->get_results($query);

        $byUrl = [];
        foreach ($results as $obj) {
            $byUrl[$obj->meta_value] = (int) $obj->ID;
        }

        return $byUrl;
    }

    public static function getAttachmentByCdnUrl($cdnUrl)
    {
        $attachments = get_posts(
            [
                'post_type' => 'attachment',
                'posts_per_page' => 1,
                'meta_key' => 'cdn_url',
                'meta_value' => $cdnUrl,
                'post_status' => ['publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit'],
            ]
        );

        return current($attachments);
    }

    public static function attachmentExists($original_url): bool
    {
        $attachment = self::getAttachment($original_url);

        return false !== $attachment;
    }

    public static function createAttachment($original_url): ?int
    {
        if (empty($original_url) && !is_string($original_url)) {
            return null;
        }

        $wp_upload_dir = wp_upload_dir();
        if (!wp_is_writable($wp_upload_dir['basedir'])) {
            throw new \Exception('Failed creating attachment, upload directory "'.$wp_upload_dir['basedir'].'" is not writeable.');
        }

        if (!class_exists('WP_Http')) {
            include_once ABSPATH.WPINC.'/class-http.php';
        }

        $url = self::fixUrl($original_url);

        $response = self::tryDownloadFile($url);

        $upload = WordpressExceptionThrower::throwExceptionOnWpError(
            wp_upload_bits(basename($url), null, $response['body'])
        );

        if (!isset($upload['file'])) {
            throw new \Exception('Failed moving downloaded file to uploads directory: '.$wp_upload_dir['path']);
        }

        $file_path = $upload['file'];
        $attachment = self::getAttachment($original_url);
        if (false !== $attachment) {
            $old_file_path = get_attached_file($attachment->ID);
            if (md5_file($old_file_path) === md5_file($file_path)) {
                unlink($file_path);

                return $attachment->ID;
            }
        }

        $file_name = basename($file_path);
        $file_type = wp_check_filetype($file_name, null);
        $attachment_title = sanitize_file_name(pathinfo($file_name, PATHINFO_FILENAME));

        $post_info = [
            'guid' => $wp_upload_dir['url'].'/'.$file_name,
            'post_mime_type' => $file_type['type'],
            'post_title' => $attachment_title,
            'post_content' => '',
            'post_status' => 'inherit',
        ];

        // Create the attachment
        $attach_id = WordpressExceptionThrower::throwExceptionOnWpError(
            wp_insert_attachment($post_info, $file_path, 0, true)
        );

        // Include image.php
        require_once ABSPATH.'wp-admin/includes/image.php';

        // Define attachment metadata
        $attach_data = WordpressExceptionThrower::throwExceptionOnWpError(
            wp_generate_attachment_metadata($attach_id, $file_path)
        );

        // Assign metadata to attachment
        wp_update_attachment_metadata($attach_id, $attach_data);
        update_post_meta($attach_id, 'original_url', $original_url);

        return get_post($attach_id)->ID;
    }

    public static function createAttachmentUsingCDN($cdnImageUrl): ?int
    {
        if (empty($cdnImageUrl) || !is_string($cdnImageUrl)) {
            throw new \RuntimeException('CDN image URL is invalid');
        }

        $cdnFileUrl = self::fixUrl($cdnImageUrl);
        $sanitizedCdnUrl = urlencode($cdnFileUrl); // Encode URL path so that {variant} won't be removed when creating attachment

        $fullImageSizeUrl = str_replace(self::CDN_URL_VARIANT_PLACEHOLDER_KEY, self::getImageScaleVariantString(), $cdnFileUrl);
        $attachment = self::getAttachment($fullImageSizeUrl);

        if (false !== $attachment) {
            return $attachment->ID;
        }

        $fileName = basename($cdnFileUrl);
        $fileType = wp_check_filetype($fileName, null);
        $attachmentTitle = sanitize_file_name(pathinfo($fileName, PATHINFO_FILENAME));

        $postInfo = [
            'post_mime_type' => $fileType['type'],
            'post_title' => $attachmentTitle,
            'post_content' => '',
            'post_status' => 'inherit',
        ];

        // Create the attachment
        $attachmentId = WordpressExceptionThrower::throwExceptionOnWpError(
            wp_insert_attachment($postInfo, $fullImageSizeUrl, 0, true)
        );

        // Include image.php
        require_once ABSPATH.'wp-admin/includes/image.php';

        $imageMeta = self::createImageMeta($fullImageSizeUrl, $cdnFileUrl);

        // Assign metadata to attachment
        wp_update_attachment_metadata($attachmentId, $imageMeta);
        update_post_meta($attachmentId, 'original_url', $fullImageSizeUrl);
        update_post_meta($attachmentId, 'cdn_url', $sanitizedCdnUrl);
        update_post_meta($attachmentId, 'is_cdn', true);

        return get_post($attachmentId)->ID;
    }

    public static function createImageMeta(string $fullImageSizeUrl, string $placeholderUrl): array
    {
        $fullImageSize = wp_getimagesize($fullImageSizeUrl);
        [$fullImageWidth, $fullImageHeight] = $fullImageSize;

        // Default image meta.
        $imageMeta = [
            'width' => $fullImageWidth,
            'height' => $fullImageHeight,
            'file' => $fullImageSizeUrl,
            'sizes' => [],
        ];

        $registeredImageSubSizes = wp_get_registered_image_subsizes();
        foreach ($registeredImageSubSizes as $name => $metadata) {
            $width = $metadata['width'];
            $height = $metadata['height'];
            $path = str_replace(self::CDN_URL_VARIANT_PLACEHOLDER_KEY, self::getImageScaleVariantString($width, $height), $placeholderUrl);
            $imageMeta['sizes'][$name] = [
                'width' => $width,
                'height' => $height,
                'file' => wp_basename($path),
                'path' => $path,
            ];
        }

        return $imageMeta;
    }

    /**
     * Returns the variant name compatible for CDN.
     *
     * @throws \Exception
     */
    public static function getImageScaleVariantString($width = null, $height = null): string
    {
        $imageCdnPrefix = StoreKeeperOptions::get(StoreKeeperOptions::IMAGE_CDN_PREFIX);

        if (empty($imageCdnPrefix)) {
            // Sync shop info
            $shopInfoSync = new SyncWoocommerceShopInfo();
            $shopInfoSync->execute([], []);
        }

        $imageCdnPrefix = StoreKeeperOptions::get(StoreKeeperOptions::IMAGE_CDN_PREFIX);

        if (empty($imageCdnPrefix)) {
            throw new \RuntimeException('Image CDN prefix not found in shop, serving images from CDN will cause issues.');
        }

        $subSizes = wp_get_registered_image_subsizes();
        foreach ($subSizes as $sizeName => $sizeMetadata) {
            $subSizeWidth = $sizeMetadata['width'];
            $subSizeHeight = $sizeMetadata['height'];
            if ($subSizeWidth === $width && $subSizeHeight === $height) {
                return "{$imageCdnPrefix}.$sizeName";
            }
        }

        // Return full variant if nothing found in sub-sizes
        return "{$imageCdnPrefix}.".self::FULL_VARIANT_KEY;
    }

    /**
     * @return mixed
     *
     * @throws BaseException
     * @throws WordpressException
     */
    public static function downloadFile($url): array
    {
        if (Core::isTest()) {
            // fetching file
            $target = MediaHelper::getMediaPath($url);
            $file = file_get_contents($target);
            if (false === $file) {
                throw new \RuntimeException("Failed to get content of \n-File: $target\n-Url: $url");
            }

            // response
            $response = [
                'body' => $file,
                'response' => [
                    'code' => 200,
                ],
            ];
        } else {
            $http = new \WP_Http();
            $response = WordpressExceptionThrower::throwExceptionOnWpError($http->request($url));

            if (Core::isDataDump()) {
                $mediaDumDir = Core::getDumpDir().'media/';
                $target = $mediaDumDir.basename(parse_url($url)['path']);

                if (!file_exists($mediaDumDir)) {
                    mkdir($mediaDumDir, 0777, true);
                }

                if (!file_put_contents($target, $response['body'])) {
                    throw new BaseException("Failed to dump file '$target'");
                }
            }
        }

        return $response;
    }

    /**
     * @throws BaseException
     * @throws WordpressException
     */
    public static function tryDownloadFile($url)
    {
        $timesTried = 0;
        while ($timesTried < 3) {
            ++$timesTried;
            try {
                $response = WordpressExceptionThrower::throwExceptionOnWpError(
                    self::downloadFile($url)
                );

                if (200 !== $response['response']['code']) {
                    throw new \Exception('Failed downloading file from "'.$url.'" with status code: '.$response['response']['code']);
                }
                break;
            } catch (\Throwable $error) {
                if (3 === $timesTried) {
                    throw $error;
                }
                sleep(1);
            }
        }

        return $response;
    }

    /**
     * Removes the prepended WordPress upload path in the URL if attachment comes from CDN.
     *
     * @see wp_get_attachment_url
     */
    public function getAttachmentUrl($attachmentUrl, $attachmentId): string
    {
        if ($this->isAttachmentCdn($attachmentId)) {
            $attachmentUrl = self::cleanAttachmentUrl($attachmentUrl);
        }

        return urldecode($attachmentUrl);
    }

    /**
     * Changes the image source to CDN variant if a size matches.
     * Removes the prepended WordPress upload path in the URL if attachment comes from CDN otherwise.
     * Returns original file path otherwise.
     *
     * @see wp_get_attachment_image_src
     */
    public function getAttachmentImageSource($attachmentImage, $attachmentId, $attachmentSize, $isAttachmentIcon)
    {
        [$attachmentUrl, $attachmentWidth, $attachmentHeight] = $attachmentImage;

        // Only change the url of attachment if it has upload directory
        // as correct URL is already being set during import. This is an edge case
        if ($this->isAttachmentCdn($attachmentId) && self::hasUploadDirectory($attachmentUrl)) {
            // example value will be an encoded https://cdn_host/path/to/image/scale/{variant}/file_name
            $cdnUrl = get_post_meta($attachmentId, 'cdn_url', true);
            $cdnUrl = urldecode($cdnUrl);
            $imageVariant = self::getImageScaleVariantString($attachmentWidth, $attachmentHeight);
            $attachmentUrl = str_replace(self::CDN_URL_VARIANT_PLACEHOLDER_KEY, $imageVariant, $cdnUrl);

            $attachmentImage[0] = $attachmentUrl;
        }

        return $attachmentImage;
    }

    /**
     * Modifies the srcset as proper images from CDN.
     *
     * @see wp_calculate_image_srcset
     */
    public function calculateImageSrcSet($sources, $sizeArray, $imageSrc, $imageMeta, $attachmentId)
    {
        if ($this->isAttachmentCdn($attachmentId)) {
            foreach ($sources as &$source) {
                $sourceUrl = $source['url'];
                $sourceWidth = $source['value']; // Value is width
                if (is_array($source) && false === strpos($sourceUrl, 'woocommerce-placeholder')) {
                    $cdnUrl = get_post_meta($attachmentId, 'cdn_url', true);
                    $cdnUrl = urldecode($cdnUrl);
                    $imageVariant = self::getImageScaleVariantString();

                    // Gets the closest possible variant/size based on width
                    // Try matching with infinite/undetermined height first
                    $intermediateSize = image_get_intermediate_size($attachmentId, [$sourceWidth, 0]);

                    if (!$intermediateSize) {
                        // Try matching with same width and height
                        $intermediateSize = image_get_intermediate_size($attachmentId, [$sourceWidth, $sourceWidth]);
                    }

                    if ($intermediateSize) {
                        $intermediateSizeWidth = $intermediateSize['width'];
                        // Intermediate sizes return 1 instead of 0, but it is not registered in sub-sizes, so we force it to be 0 here
                        $intermediateSizeHeight = 1 === $intermediateSize['height'] ? 0 : $intermediateSize['height'];
                        $imageVariant = self::getImageScaleVariantString($intermediateSizeWidth, $intermediateSizeHeight);
                    }

                    $sourceUrl = str_replace(self::CDN_URL_VARIANT_PLACEHOLDER_KEY, $imageVariant, $cdnUrl);
                    $source['url'] = $sourceUrl;
                }
            }
        }

        return $sources;
    }

    /**
     * Removes the prepended WordPress upload path in the URL.
     */
    public static function cleanAttachmentUrl(string $attachmentUrl)
    {
        // Instead of keeping full path we actually need just 'wp-content/uploads'.
        // and we do this the right way, dynamically, calling functions and constants.
        $uploadsDir = wp_get_upload_dir()['basedir'];
        $partialUploadsDir = str_replace(ABSPATH, '', $uploadsDir);

        // Check if attachment file is in WordPress uploads directory.
        if (false === strpos($attachmentUrl, $partialUploadsDir)) {
            return $attachmentUrl;
        }

        $pattern = get_site_url().'/'.$partialUploadsDir;

        return preg_replace("#$pattern\/#", '', $attachmentUrl);
    }

    /**
     * Checks if the URL has wordpress uploads path.
     */
    public static function hasUploadDirectory(string $attachmentUrl): bool
    {
        $uploadsDir = wp_get_upload_dir()['basedir'];
        $partialUploadsDir = str_replace(ABSPATH, '', $uploadsDir);

        return false !== strpos($attachmentUrl, $partialUploadsDir);
    }

    protected function isAttachmentCdn($attachmentId): bool
    {
        return (bool) get_post_meta($attachmentId, 'is_cdn', true);
    }
}
