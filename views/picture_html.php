<?php

    $url =  $this->request->getFullUrlPath();
    $path = parse_url($url, PHP_URL_PATH);
    $segments = explode('/', rtrim($path, '/'));
    $id = end($segments);

    $pathToUpload = __CA_URL_ROOT__.'/index.php/bigPicture/Pictures/Upload';
    echo '<form method="POST" action="'.$pathToUpload.'" enctype="multipart/form-data">';
?>
    <?php
        echo '<input type="hidden" name="name" value="'.$id.'">';
    ?>
    <div>
        <label for="">Fichiers : </label>
        <input name="file" type="file" />
    </div>
    <br>
    <hr>
    <div>
        <input type="submit" name="bigPic" value="Envoyer le fichier">
    </div>
</form>

