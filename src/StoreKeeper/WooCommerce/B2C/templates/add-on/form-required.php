<?php

$type = $addon['type'];
echo '<div class="sk-addon-select sk-addon-'.$type.'">';
echo '<p class="sk-addon-title">'.esc_html($addon['title']).'</p>';
echo '<ul>';
foreach ($addon['options'] as $option) {
    echo '<li>'.$option['title'].'</li>';
}
echo '</ul>';
echo '</div>';
