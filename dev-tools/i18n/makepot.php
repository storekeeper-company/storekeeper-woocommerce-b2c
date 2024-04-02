<?php

require_once dirname(__FILE__).'/not-gettexted.php';
require_once dirname(__FILE__).'/pot-ext-meta.php';
require_once dirname(__FILE__).'/extract.php';

if (!defined('STDERR')) {
    define('STDERR', fopen('php://stderr', 'w'));
}

/**
 * Class to create POT files for
 *  - WordPress 3.4+
 *  - WordPress plugins
 *  - WordPress themes
 *  - GlotPress (standalone)
 *  - WordPress.org projects (Rosetta, forums, directories)
 *  - WordCamp.org.
 *
 * Support for older projects can be found in the legacy branch:
 * https://i18n.trac.wordpress.org/browser/tools/branches/legacy
 */
class MakePOT
{
    private $max_header_lines = 30;

    public $projects = [
        'generic',
        'wp-frontend',
        'wp-admin',
        'wp-network-admin',
        'wp-tz',
        'wp-plugin',
        'wp-theme',
        'glotpress',
        'rosetta',
        'wporg-bb-forums',
        'wporg-themes',
        'wporg-plugins',
        'wporg-forums',
        'wordcamporg',
    ];

    public $rules = [
        '_' => ['string'],
        '__' => ['string'],
        '_e' => ['string'],
        '_c' => ['string'],
        '_n' => ['singular', 'plural'],
        '_n_noop' => ['singular', 'plural'],
        '_nc' => ['singular', 'plural'],
        '__ngettext' => ['singular', 'plural'],
        '__ngettext_noop' => ['singular', 'plural'],
        '_x' => ['string', 'context'],
        '_ex' => ['string', 'context'],
        '_nx' => ['singular', 'plural', null, 'context'],
        '_nx_noop' => ['singular', 'plural', 'context'],
        '_n_js' => ['singular', 'plural'],
        '_nx_js' => ['singular', 'plural', 'context'],
        'esc_attr__' => ['string'],
        'esc_html__' => ['string'],
        'esc_attr_e' => ['string'],
        'esc_html_e' => ['string'],
        'esc_attr_x' => ['string', 'context'],
        'esc_html_x' => ['string', 'context'],
        'comments_number_link' => ['string', 'singular', 'plural'],
    ];

    private $ms_files = [
        'ms-.*',
        '.*/ms-.*',
        '.*/my-.*',
        'wp-activate\.php',
        'wp-signup\.php',
        'wp-admin/network\.php',
        'wp-admin/network/.*\.php',
        'wp-admin/includes/ms\.php',
        'wp-admin/includes/class-wp-ms.*',
        'wp-admin/includes/network\.php',
    ];

    private $temp_files = [];
    private StringExtractor $extractor;

    public $meta = [
        'default' => [
            'from-code' => 'utf-8',
            'msgid-bugs-address' => 'https://make.wordpress.org/polyglots/',
            'language' => 'php',
            'add-comments' => 'translators',
            'comments' => "Copyright (C) {year} {package-name}\nThis file is distributed under the same license as the {package-name} package.",
        ],
        'generic' => [],
        'wp-frontend' => [
            'description' => 'Translation of frontend strings in WordPress {version}',
            'copyright-holder' => 'WordPress',
            'package-name' => 'WordPress',
            'package-version' => '{version}',
        ],
        'wp-admin' => [
            'description' => 'Translation of site admin strings in WordPress {version}',
            'copyright-holder' => 'WordPress',
            'package-name' => 'WordPress',
            'package-version' => '{version}',
        ],
        'wp-network-admin' => [
            'description' => 'Translation of network admin strings in WordPress {version}',
            'copyright-holder' => 'WordPress',
            'package-name' => 'WordPress',
            'package-version' => '{version}',
        ],
        'wp-tz' => [
            'description' => 'Translation of timezone strings in WordPress {version}',
            'copyright-holder' => 'WordPress',
            'package-name' => 'WordPress',
            'package-version' => '{version}',
        ],
        'wp-plugin' => [
            'description' => 'Translation of the WordPress plugin {name} {version} by {author}',
            'msgid-bugs-address' => 'https://wordpress.org/support/plugin/{slug}',
            'copyright-holder' => '{author}',
            'package-name' => '{name}',
            'package-version' => '{version}',
        ],
        'wp-theme' => [
            'description' => 'Translation of the WordPress theme {name} {version} by {author}',
            'msgid-bugs-address' => 'https://wordpress.org/support/theme/{slug}',
            'copyright-holder' => '{author}',
            'package-name' => '{name}',
            'package-version' => '{version}',
            'comments' => 'Copyright (C) {year} {author}\nThis file is distributed under the same license as the {package-name} package.',
        ],
        'glotpress' => [
            'description' => 'Translation of GlotPress',
            'copyright-holder' => 'GlotPress',
            'package-name' => 'GlotPress',
        ],
        'wporg-bb-forums' => [
            'description' => 'WordPress.org International Forums',
            'copyright-holder' => 'WordPress',
            'package-name' => 'WordPress.org International Forums',
        ],
        'wporg' => [
            'description' => 'WordPress.org',
            'copyright-holder' => 'WordPress',
            'package-name' => 'WordPress.org',
        ],
        'wordcamporg' => [
            'description' => 'WordCamp.org',
            'copyright-holder' => 'WordPress',
            'package-name' => 'WordCamp.org',
        ],
        'rosetta' => [
            'description' => 'Rosetta (.wordpress.org locale sites)',
            'copyright-holder' => 'WordPress',
            'package-name' => 'Rosetta',
        ],
    ];

    public function __construct($deprecated = true)
    {
        $this->extractor = new StringExtractor($this->rules);
    }

    public function __destruct()
    {
        foreach ($this->temp_files as $temp_file) {
            unlink($temp_file);
        }
    }

    private function tempnam($file)
    {
        $tempnam = tempnam(sys_get_temp_dir(), $file);
        $this->temp_files[] = $tempnam;

        return $tempnam;
    }

    private function realpath_missing($path)
    {
        return realpath(dirname($path)).DIRECTORY_SEPARATOR.basename($path);
    }

    private function xgettext(
        $project,
        $dir,
        $output_file,
        $placeholders = [],
        $excludes = [],
        $includes = []
    ) {
        $meta = array_merge($this->meta['default'], $this->meta[$project]);
        $placeholders = array_merge($meta, $placeholders);
        $meta['output'] = $this->realpath_missing($output_file);
        $placeholders['year'] = date('Y');
        $placeholder_keys = array_map(
            function ($x) {return '{'.$x.'}'; }, array_keys($placeholders));
        $placeholder_values = array_values($placeholders);
        foreach ($meta as $key => $value) {
            $meta[$key] = str_replace($placeholder_keys, $placeholder_values, $value);
        }

        $originals = $this->extractor->extract_from_directory($dir, $excludes, $includes);
        $pot = new PO();
        $pot->entries = $originals->entries;

        $pot->set_header('Project-Id-Version', $meta['package-name'].' '.$meta['package-version']);
        $pot->set_header('Report-Msgid-Bugs-To', $meta['msgid-bugs-address']);
        $pot->set_header('POT-Creation-Date', gmdate('Y-m-d H:i:s+00:00'));
        $pot->set_header('MIME-Version', '1.0');
        $pot->set_header('Content-Type', 'text/plain; charset=UTF-8');
        $pot->set_header('Content-Transfer-Encoding', '8bit');
        $pot->set_header('PO-Revision-Date', date('Y').'-MO-DA HO:MI+ZONE');
        $pot->set_header('Last-Translator', 'FULL NAME <EMAIL@ADDRESS>');
        $pot->set_header('Language-Team', 'LANGUAGE <LL@li.org>');
        $pot->set_comment_before_headers($meta['comments']);
        $pot->export_to_file($output_file);

        return true;
    }

    public function wp_generic($dir, $args)
    {
        $defaults = [
            'project' => 'wp-core',
            'output' => null,
            'default_output' => 'wordpress.pot',
            'includes' => [],
            'excludes' => array_merge(
                ['wp-admin/includes/continents-cities\.php', 'wp-content/themes/twenty.*'],
                $this->ms_files
            ),
            'extract_not_gettexted' => false,
            'not_gettexted_files_filter' => false,
        ];
        $args = array_merge($defaults, $args);
        extract($args);
        $placeholders = [];
        if ($wp_version = $this->wp_version($dir)) {
            $placeholders['version'] = $wp_version;
        } else {
            return false;
        }
        $output = is_null($output) ? $default_output : $output;
        $res = $this->xgettext($project, $dir, $output, $placeholders, $excludes, $includes);
        if (!$res) {
            return false;
        }

        if ($extract_not_gettexted) {
            $old_dir = getcwd();
            $output = realpath($output);
            chdir($dir);
            $php_files = NotGettexted::list_php_files('.');
            $php_files = array_filter($php_files, $not_gettexted_files_filter);
            $not_gettexted = new NotGettexted();
            $res = $not_gettexted->command_extract($output, $php_files);
            chdir($old_dir);
            /* Adding non-gettexted strings can repeat some phrases */
            $output_shell = escapeshellarg($output);
            system("msguniq --use-first $output_shell -o $output_shell");
        }

        return $res;
    }

    public function wp_frontend($dir, $output)
    {
        if (!file_exists("$dir/wp-admin/user/about.php")) {
            return false;
        }

        $excludes = ['wp-admin/.*', 'wp-content/themes/.*', 'wp-includes/class-pop3\.php'];

        // Exclude Akismet all together for 3.9+.
        if (file_exists("$dir/wp-admin/css/about.css")) {
            $excludes[] = 'wp-content/plugins/akismet/.*';
        }

        return $this->wp_generic(
            $dir,
            [
                'project' => 'wp-frontend',
                'output' => $output,
                'includes' => [],
                'excludes' => $excludes,
                'default_output' => 'wordpress.pot',
            ]
        );
    }

    public function wp_admin($dir, $output)
    {
        $frontend_pot = $this->tempnam('frontend.pot');
        if (false === $frontend_pot) {
            return false;
        }

        $frontend_result = $this->wp_frontend($dir, $frontend_pot);
        if (!$frontend_result) {
            return false;
        }

        $network_admin_files = $this->get_wp_network_admin_files($dir);

        $result = $this->wp_generic(
            $dir,
            [
                'project' => 'wp-admin',
                'output' => $output,
                'includes' => ['wp-admin/.*'],
                'excludes' => array_merge(['wp-admin/includes/continents-cities\.php'], $network_admin_files),
                'default_output' => 'wordpress-admin.pot',
            ]
        );
        if (!$result) {
            return false;
        }

        $potextmeta = new PotExtMeta();

        if (!file_exists("$dir/wp-admin/css/about.css")) { // < 3.9
            $result = $potextmeta->append("$dir/wp-content/plugins/akismet/akismet.php", $output);
            if (!$result) {
                return false;
            }
        }

        $result = $potextmeta->append("$dir/wp-content/plugins/hello.php", $output);
        if (!$result) {
            return false;
        }

        /* Adding non-gettexted strings can repeat some phrases */
        $output_shell = escapeshellarg($output);
        system("msguniq $output_shell -o $output_shell");

        $common_pot = $this->tempnam('common.pot');
        if (!$common_pot) {
            return false;
        }
        $admin_pot = realpath(is_null($output) ? 'wordpress-admin.pot' : $output);
        system("msgcat --more-than=1 --use-first $frontend_pot $admin_pot > $common_pot");
        system("msgcat -u --use-first $admin_pot $common_pot -o $admin_pot");

        return true;
    }

    public function wp_network_admin($dir, $output)
    {
        if (!file_exists("$dir/wp-admin/user/about.php")) {
            return false;
        }

        $frontend_pot = $this->tempnam('frontend.pot');
        if (false === $frontend_pot) {
            return false;
        }

        $frontend_result = $this->wp_frontend($dir, $frontend_pot);
        if (!$frontend_result) {
            return false;
        }

        $admin_pot = $this->tempnam('admin.pot');
        if (false === $admin_pot) {
            return false;
        }

        $admin_result = $this->wp_admin($dir, $admin_pot);
        if (!$admin_result) {
            return false;
        }

        $result = $this->wp_generic(
            $dir,
            [
                'project' => 'wp-network-admin',
                'output' => $output,
                'includes' => $this->get_wp_network_admin_files($dir),
                'excludes' => [],
                'default_output' => 'wordpress-admin-network.pot',
            ]
        );

        if (!$result) {
            return false;
        }

        $common_pot = $this->tempnam('common.pot');
        if (!$common_pot) {
            return false;
        }

        $net_admin_pot = realpath(is_null($output) ? 'wordpress-network-admin.pot' : $output);
        system("msgcat --more-than=1 --use-first $frontend_pot $admin_pot $net_admin_pot > $common_pot");
        system("msgcat -u --use-first $net_admin_pot $common_pot -o $net_admin_pot");

        return true;
    }

    private function get_wp_network_admin_files($dir)
    {
        $wp_version = $this->wp_version($dir);

        // https://core.trac.wordpress.org/ticket/19852
        $files = ['wp-admin/network/.*', 'wp-admin/network.php'];

        // https://core.trac.wordpress.org/ticket/34910
        if (version_compare($wp_version, '4.5-beta', '>=')) {
            $files = array_merge(
                $files,
                [
                    'wp-admin/includes/class-wp-ms.*',
                    'wp-admin/includes/network.php',
                ]
            );
        }

        return $files;
    }

    public function wp_tz($dir, $output)
    {
        return $this->wp_generic(
            $dir,
            [
                'project' => 'wp-tz',
                'output' => $output,
                'includes' => ['wp-admin/includes/continents-cities\.php'],
                'excludes' => [],
                'default_output' => 'wordpress-continents-cities.pot',
            ]
        );
    }

    private function wp_version($dir)
    {
        $version_php = $dir.'/wp-includes/version.php';
        if (!is_readable($version_php)) {
            return false;
        }

        return preg_match(
            '/\$wp_version\s*=\s*\'(.*?)\';/',
            file_get_contents($version_php),
            $matches
        ) ? $matches[1] : false;
    }

    public function get_first_lines($filename, $lines = 30)
    {
        $extf = fopen($filename, 'r');
        if (!$extf) {
            return false;
        }
        $first_lines = '';
        foreach (range(1, $lines) as $x) {
            $line = fgets($extf);
            if (feof($extf)) {
                break;
            }
            if (false === $line) {
                return false;
            }
            $first_lines .= $line;
        }

        // PHP will close file handle, but we are good citizens.
        fclose($extf);

        // Make sure we catch CR-only line endings.
        $first_lines = str_replace("\r", "\n", $first_lines);

        return $first_lines;
    }

    public function get_addon_header($header, &$source)
    {
        /*
         * A few things this needs to handle:
         * - 'Header: Value\n'
         * - '// Header: Value'
         * - '/* Header: Value * /'
         * - '<?php // Header: Value ?>'
         * - '<?php /* Header: Value * / $foo='bar'; ?>'
         */
        if (preg_match('/^(?:[ \t]*<\?php)?[ \t\/*#@]*'.preg_quote($header, '/').':(.*)$/mi', $source, $matches)) {
            return $this->_cleanup_header_comment($matches[1]);
        } else {
            return false;
        }
    }

    /**
     * Removes any trailing closing comment / PHP tags from the header value.
     */
    private function _cleanup_header_comment($str)
    {
        return trim(preg_replace('/\s*(?:\*\/|\?>).*/', '', $str));
    }

    public function generic($dir, $output)
    {
        $output = is_null($output) ? 'generic.pot' : $output;

        return $this->xgettext('generic', $dir, $output, []);
    }

    private function guess_plugin_slug($dir)
    {
        if ('trunk' == basename($dir)) {
            $slug = basename(dirname($dir));
        } elseif (in_array(basename(dirname($dir)), ['branches', 'tags'])) {
            $slug = basename(dirname(dirname($dir)));
        } else {
            $slug = basename($dir);
        }

        return $slug;
    }

    public function wp_plugin($dir, $output, $slug = null, array $args = [])
    {
        $defaults = [
            'excludes' => [],
            'includes' => [],
        ];
        $args += $defaults;
        $placeholders = [];
        // guess plugin slug
        if (is_null($slug)) {
            $slug = $this->guess_plugin_slug($dir);
        }

        $plugins_dir = @opendir($dir);
        $plugin_files = [];
        if ($plugins_dir) {
            while (($file = readdir($plugins_dir)) !== false) {
                if ('.' === substr($file, 0, 1)) {
                    continue;
                }

                if ('.php' === substr($file, -4)) {
                    $plugin_files[] = $file;
                }
            }
            closedir($plugins_dir);
        }

        if (empty($plugin_files)) {
            return false;
        }

        $main_file = '';
        foreach ($plugin_files as $plugin_file) {
            if (!is_readable("$dir/$plugin_file")) {
                continue;
            }

            $source = $this->get_first_lines("$dir/$plugin_file", $this->max_header_lines);

            // Stop when we find a file with a plugin name header in it.
            if (false != $this->get_addon_header('Plugin Name', $source)) {
                $main_file = "$dir/$plugin_file";
                break;
            }
        }

        if (empty($main_file)) {
            return false;
        }

        $placeholders['version'] = $this->get_addon_header('Version', $source);
        $placeholders['author'] = $this->get_addon_header('Author', $source);
        $placeholders['name'] = $this->get_addon_header('Plugin Name', $source);
        $placeholders['slug'] = $slug;

        $output = is_null($output) ? "$slug.pot" : $output;
        $res = $this->xgettext('wp-plugin', $dir, $output, $placeholders, $args['excludes'], $args['includes']);
        if (!$res) {
            return false;
        }
        $potextmeta = new PotExtMeta();
        $res = $potextmeta->append($main_file, $output);
        /* Adding non-gettexted strings can repeat some phrases */
        $output_shell = escapeshellarg($output);
        system("msguniq $output_shell -o $output_shell");

        return $res;
    }

    public function wp_theme($dir, $output, $slug = null)
    {
        $placeholders = [];
        // guess plugin slug
        if (is_null($slug)) {
            $slug = $this->guess_plugin_slug($dir);
        }
        $main_file = $dir.'/style.css';
        $source = $this->get_first_lines($main_file, $this->max_header_lines);

        $placeholders['version'] = $this->get_addon_header('Version', $source);
        $placeholders['author'] = $this->get_addon_header('Author', $source);
        $placeholders['name'] = $this->get_addon_header('Theme Name', $source);
        $placeholders['slug'] = $slug;

        $license = $this->get_addon_header('License', $source);
        if ($license) {
            $this->meta['wp-theme']['comments'] = "Copyright (C) {year} {author}\nThis file is distributed under the {$license}.";
        } else {
            $this->meta['wp-theme']['comments'] = "Copyright (C) {year} {author}\nThis file is distributed under the same license as the {package-name} package.";
        }

        $output = is_null($output) ? "$slug.pot" : $output;
        $res = $this->xgettext('wp-theme', $dir, $output, $placeholders);
        if (!$res) {
            return false;
        }
        $potextmeta = new PotExtMeta();
        $res = $potextmeta->append(
            $main_file,
            $output,
            ['Theme Name', 'Theme URI', 'Description', 'Author', 'Author URI']
        );
        if (!$res) {
            return false;
        }
        // If we're dealing with a pre-3.4 default theme, don't extract page templates before 3.4.
        $extract_templates = !in_array($slug, ['twentyten', 'twentyeleven', 'default', 'classic']);
        if (!$extract_templates) {
            $wp_dir = dirname(dirname(dirname($dir)));
            $extract_templates = file_exists("$wp_dir/wp-admin/user/about.php") || !file_exists("$wp_dir/wp-load.php");
        }
        if ($extract_templates) {
            $res = $potextmeta->append($dir, $output, ['Template Name']);
            if (!$res) {
                return false;
            }
            $files = scandir($dir);
            foreach ($files as $file) {
                if ('.' == $file[0] || 'CVS' == $file) {
                    continue;
                }
                if (is_dir($dir.'/'.$file)) {
                    $res = $potextmeta->append($dir.'/'.$file, $output, ['Template Name']);
                    if (!$res) {
                        return false;
                    }
                }
            }
        }
        /* Adding non-gettexted strings can repeat some phrases */
        $output_shell = escapeshellarg($output);
        system("msguniq $output_shell -o $output_shell");

        return $res;
    }

    public function glotpress($dir, $output)
    {
        $output = is_null($output) ? 'glotpress.pot' : $output;

        return $this->xgettext('glotpress', $dir, $output);
    }

    public function wporg_bb_forums($dir, $output)
    {
        $output = is_null($output) ? 'wporg.pot' : $output;

        return $this->xgettext(
            'wporg-bb-forums',
            $dir,
            $output,
            [],
            [
                'bb-plugins/elfakismet/.*',
                'bb-plugins/support-forum/.*',
                'themes/.*',
            ]
        );
    }

    public function wporg_themes($dir, $output)
    {
        $output = is_null($output) ? 'wporg-themes.pot' : $output;

        return $this->xgettext(
            'wporg',
            $dir,
            $output,
            [],
            [],
            [
                'plugins/theme-directory/.*',
                'themes/pub/wporg-themes/.*',
            ]
        );
    }

    public function wporg_plugins($dir, $output)
    {
        $output = is_null($output) ? 'wporg-plugins.pot' : $output;

        return $this->xgettext(
            'wporg',
            $dir,
            $output,
            [],
            [
                'plugins/svn-track/i18n-tools/.*',
            ],
            [
                '.*\.php',
            ]
        );
    }

    public function wporg_forums($dir, $output)
    {
        $output = is_null($output) ? 'wporg-forums.pot' : $output;

        return $this->xgettext(
            'wporg',
            $dir,
            $output,
            [],
            [],
            [
                '.*\.php',
            ]
        );
    }

    public function wordcamporg($dir, $output)
    {
        $output = is_null($output) ? 'wordcamporg.pot' : $output;

        return $this->xgettext(
            'wordcamporg',
            $dir,
            $output,
            [],
            [],
            [
                '.*\.php',
            ]
        );
    }

    public function rosetta($dir, $output)
    {
        $output = is_null($output) ? 'rosetta.pot' : $output;

        return $this->xgettext(
            'rosetta',
            $dir,
            $output,
            [],
            [
                'mu-plugins/rosetta/i18n-tools/.*',
                'mu-plugins/rosetta/locales/.*',
            ],
            [
                'mu-plugins/(roles|showcase|downloads)/.*\.php',
                'mu-plugins/jetpack-settings.php',
                'mu-plugins/rosetta.*\.php',
                'mu-plugins/rosetta/[^/]+\.php',
                'mu-plugins/rosetta/tmpl/.*\.php',
                'themes/rosetta/.*\.php',
            ]
        );
    }
}

// run the CLI only if the file
// wasn't included
$included_files = get_included_files();
if (__FILE__ == $included_files[0]) {
    $makepot = new MakePOT();
    if ((3 == count($argv) || 4 == count($argv)) && in_array(
        $method = str_replace('-', '_', $argv[1]),
        get_class_methods($makepot)
    )) {
        $res = call_user_func([$makepot, $method], realpath($argv[2]), isset($argv[3]) ? $argv[3] : null);
        if (false === $res) {
            fwrite(STDERR, "Couldn't generate POT file!\n");
        }
    } else {
        $usage = "Usage: php makepot.php PROJECT DIRECTORY [OUTPUT]\n\n";
        $usage .= "Generate POT file from the files in DIRECTORY [OUTPUT]\n";
        $usage .= 'Available projects: '.implode(', ', $makepot->projects)."\n";
        fwrite(STDERR, $usage);
        exit(1);
    }
}
