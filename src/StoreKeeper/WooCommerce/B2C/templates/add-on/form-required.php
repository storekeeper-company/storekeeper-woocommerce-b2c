<?php

echo '<div class="sk-addon-select">';
echo '<p class="sk-addon-title">'.$addon['title'].'</p>';
echo '<ul>';
foreach ($addon['options'] as $option) {
    echo '<li>'.$option['title'].'</li>';
}
echo '</ul>';
echo '</div>';
