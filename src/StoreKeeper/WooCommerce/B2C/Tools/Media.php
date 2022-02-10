<?php

namespace StoreKeeper\WooCommerce\B2C\Tools;

use StoreKeeper\WooCommerce\B2C\Core;
use StoreKeeper\WooCommerce\B2C\Exceptions\BaseException;
use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\TestLib\MediaHelper;

class Media
{
    public static function fixUrl($url)
    {
        if (StringFunctions::startsWith($url, '/')) {
            $server = StoreKeeperOptions::get(StoreKeeperOptions::API_URL);
            $s = $server[strlen($server) - 1];
            if ('/' === $server[strlen($server) - 1]) {
                $server = rtrim($server, '/');
            }
            $url = $server.$url;
        }

        if (false !== strpos($url, '?')) {
            $url = explode('?', $url);
            array_pop($url);
            $url = join('?', $url);
        }

        return $url;
    }

    public static function getAttachmentId($original_url)
    {
        if ($attachment = self::getAttachment($original_url)) {
            return $attachment->ID;
        } else {
            return self::createAttachment($original_url);
        }
    }

    /**
     * @param $original_url
     *
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

    public static function attachmentExists($original_url)
    {
        $attachment = self::getAttachment($original_url);

        return false !== $attachment;
    }

    public static function createAttachment($original_url)
    {
        if (empty($original_url) && !is_string($original_url)) {
            return;
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

    public static function createAttachmentCDN($bigImageUrl, $smallImageUrl)
    {
        if (empty($bigImageUrl) && !is_string($bigImageUrl)) {
            return;
        }

        $url = self::fixUrl($bigImageUrl);
        $smallImageUrlFull = self::fixUrl($smallImageUrl);

        try {
            $response = self::tryDownloadFile($url);
        } catch (\Exception $exception) {
            $s = $exception;
        }

        $file_path = $url;
        $attachment = self::getAttachment($bigImageUrl);
        if (false !== $attachment) {
            $old_file_path = get_attached_file($attachment->ID);
            $uploads = wp_get_upload_dir();
            $server = StoreKeeperOptions::get(StoreKeeperOptions::API_URL);

            if (false !== strpos($old_file_path, $uploads['basedir'].'/') && false !== strpos($old_file_path, $server)) {
                $old_file_path = str_replace($uploads['basedir'].'/', '', $old_file_path);
            }
            if (md5_file($old_file_path) === md5_file($file_path)) {
                unlink($file_path);

                return $attachment->ID;
            }
        }

        $file_name = basename($file_path);
        $file_type = wp_check_filetype($file_name, null);
        $attachment_title = sanitize_file_name(pathinfo($file_name, PATHINFO_FILENAME));

        $post_info = [
            'guid' => $url,
            'post_mime_type' => $file_type['type'],
            'post_title' => $attachment_title,
            'post_content' => '',
            'post_status' => 'inherit',
        ];
//
//        // Create the attachment
        $attach_id = WordpressExceptionThrower::throwExceptionOnWpError(
            wp_insert_attachment($post_info, $file_path, 0, true)
        );
//
//        // Include image.php
        require_once ABSPATH.'wp-admin/includes/image.php';
//
        // Define attachment metadata
//        $attach_data = WordpressExceptionThrower::throwExceptionOnWpError(
//            wp_generate_attachment_metadata($attach_id, $file_path)
//        );

        $attachment = get_post($attach_id);

        $metadata = [];
        $support = false;
        $mime_type = get_post_mime_type($attachment);

        if (preg_match('!^image/!', $mime_type) && file_is_displayable_image($file_path)) {
            // Make thumbnails and other intermediate sizes.
//            $metadata = wp_create_image_subsizes( $file_path, $attach_id );
        }

        $imagesize = wp_getimagesize($file_path);

        if (empty($imagesize)) {
            // File is not an image.
            return [];
        }

        // Default image meta.
        $image_meta = [
            'width' => $imagesize[0],
            'height' => $imagesize[1],
            'file' => $file_path,
            'sizes' => [],
        ];
        $new_sizes = wp_get_registered_image_subsizes();
        $thumbnailImageSize = wp_getimagesize($smallImageUrlFull);
        $thumbnailImageMeta = [
            'width' => $thumbnailImageSize[0],
            'height' => $thumbnailImageSize[1],
            'file' => $smallImageUrlFull,
            'path' => $smallImageUrl,
        ];

        if (empty($thumbnailImageSize)) {
            // File is not an image.
            return [];
        }

        $image_meta['sizes']['thumbnail'] = $thumbnailImageMeta;

//
//        // Assign metadata to attachment
        wp_update_attachment_metadata($attach_id, $image_meta);
        update_post_meta($attach_id, 'original_url', $bigImageUrl);

        return get_post($attach_id)->ID;
    }

    /**
     * @param $url
     *
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
     * @param $url
     *
     * @return mixed
     *
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
}
