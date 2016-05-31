<?php

echo '<div id="audio-details">';
$audio_links = do_shortcode("[someShortcode]");
$php_audio_links = json_decode($audio_links, true);

foreach ($php_audio_links as $folder_name => $folder)
{
    echo "<h3>" . $folder_name . "</h3>";
    foreach ($folder as $name => $link)
    {
        echo "<a href='" . $link . "'>" . $name . "</a>";
    }
}

echo '</div>';