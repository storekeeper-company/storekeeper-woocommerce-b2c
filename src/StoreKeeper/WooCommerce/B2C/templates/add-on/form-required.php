<?php

echo '<p class="custom-checkbox-description">'.$addon['title'].'</p>'; // todo style better
echo '<ul>';
foreach ($addon['options'] as $option) {
    echo '<li>'.$option['title'].'</li>';
}
echo '</ul>';
