<?php

namespace StoreKeeper\WooCommerce\B2C\Helpers\Seo;

class StorekeeperSeo
{
    public function testCategoryUpdate($categoryId)
    {
//        $txt = "SOME OBVIOUS HTML TEXT";
//        echo <<<HTML
//            <div class="notice notice-error">
//            <p style="color: red; text-decoration: blink;">$txt</p>
//            </div>
//        HTML;
    }

    public function extra_category_fields( $tag )
    {    //check for existing featured ID
        $t_id = $tag->term_id;
        $cat_meta = get_option( "category_$t_id");
        ?>
        <tr class="form-field">
            <th scope="row" valign="top"><label for="cat_Image_url"><?php _e('Category Image Url'); ?></label></th>
            <td>
                <input type="text" name="Cat_meta[img]" id="Cat_meta[img]" size="3" style="width:60%;" value="<?php echo $cat_meta['img'] ? $cat_meta['img'] : ''; ?>"><br />
                <span class="description"><?php _e('Image for category: use full url with '); ?></span>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row" valign="top"><label for="extra1"><?php _e('extra field'); ?></label></th>
            <td>
                <input type="text" name="Cat_meta[extra1]" id="Cat_meta[extra1]" size="25" style="width:60%;" value="<?php echo $cat_meta['extra1'] ? $cat_meta['extra1'] : ''; ?>"><br />
                <span class="description"><?php _e('extra field'); ?></span>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row" valign="top"><label for="extra2"><?php _e('extra field'); ?></label></th>
            <td>
                <input type="text" name="Cat_meta[extra2]" id="Cat_meta[extra2]" size="25" style="width:60%;" value="<?php echo $cat_meta['extra2'] ? $cat_meta['extra2'] : ''; ?>"><br />
                <span class="description"><?php _e('extra field'); ?></span>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row" valign="top"><label for="extra3"><?php _e('extra field'); ?></label></th>
            <td>
                <textarea name="Cat_meta[extra3]" id="Cat_meta[extra3]" style="width:60%;"><?php echo $cat_meta['extra3'] ? $cat_meta['extra3'] : ''; ?></textarea><br />
                <span class="description"><?php _e('extra field'); ?></span>
            </td>
        </tr>
        <?php
    }

    public function th_show_all_hooks($tag)
    {
        if(is_admin()) { // Display Hooks in front end pages only
            $debug_tags = array();
            global $debug_tags;
            if ( in_array( $tag, $debug_tags ) ) {
                return;
            }
            echo "<pre>" . $tag . "</pre>";
            $debug_tags[] = $tag;
        }
    }
}