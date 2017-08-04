<?php
/* ----------------------------------------------------------------------
 * plugins/statisticsViewer/controllers/StatisticsController.php :
 * ----------------------------------------------------------------------
 * CollectiveAccess
 * Open-source collections management software
 * ----------------------------------------------------------------------
 *
 * Software by Whirl-i-Gig (http://www.whirl-i-gig.com)
 * Copyright 2010 Whirl-i-Gig
 *
 * For more information visit http://www.CollectiveAccess.org
 *
 * This program is free software; you may redistribute it and/or modify it under
 * the terms of the provided license as published by Whirl-i-Gig
 *
 * CollectiveAccess is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTIES whatsoever, including any implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * This source code is free and modifiable under the terms of
 * GNU General Public License. (http://www.gnu.org/copyleft/gpl.html). See
 * the "license.txt" file for details, or visit the CollectiveAccess web site at
 * http://www.CollectiveAccess.org
 *
 * ----------------------------------------------------------------------
 */

require_once (__CA_APP_DIR__.'/helpers/importHelpers.php');
require_once(__CA_LIB_DIR__.'/core/TaskQueue.php');
require_once(__CA_LIB_DIR__.'/core/Configuration.php');
require_once(__CA_MODELS_DIR__.'/ca_lists.php');
require_once(__CA_MODELS_DIR__.'/ca_objects.php');
require_once(__CA_MODELS_DIR__.'/ca_object_representations.php');
require_once(__CA_MODELS_DIR__.'/ca_object_representations_x_object_representations.php');
require_once(__CA_MODELS_DIR__.'/ca_locales.php');


class PicturesController extends ActionController
{
    # -------------------------------------------------------
    protected $opo_config;        // plugin configuration file
    protected $opa_dir_list;    // list of available import directories
    protected $opa_regexes;        // list of available regular expression packages for extracting object idno's from filenames
    protected $opa_regex_patterns;
    protected $opa_locales;
    protected $opa_statistics_xml_files;
    protected $opa_statistics;
    protected $opa_stat;
    protected $opa_id;
    protected $pa_parameters;
    protected $allowed_universes;

    # -------------------------------------------------------
    # Constructor
    # -------------------------------------------------------
    public function __construct(&$po_request, &$po_response, $pa_view_paths = null)
    {
        global $allowed_universes;
        parent::__construct($po_request, $po_response, $pa_view_paths);
        $this->opo_config = Configuration::load(__CA_APP_DIR__ . '/plugins/bigPicture/conf/bigPicture.conf');
    }

    # -------------------------------------------------------
    # Functions to render views
    # -------------------------------------------------------
    public function Index($type = "")
    {
        $this->render('picture_html.php');
    }

    private function getDimensions($filepath)
    {
        // Getting the dimensions of the pictures
        $dimensions = getimagesize($filepath);
        $width = $dimensions[0];
        $height = $dimensions[1];
        $mimeType = $dimensions["mime"];
        return array(
            "dimensions" => $dimensions,
            "width" => $width,
            "height" => $height,
            "mimetype" => $mimeType
        );
    }

    # -------------------------------------------------------
    public function Upload($type = "")
    {
        //error_reporting(E_ALL);
        $typeAccepted = $this->opo_config->getAssoc('TypeAccepted');
        $targetDir = __CA_BASE_DIR__ . '/media/bigPicture/';
        $debug = 1;
        
        $log_file = $targetDir."/log.txt";
        if (!is_dir($targetDir)) {
            $result = mkdir($targetDir, 0777, true);
        }
        $targetFilePath = $targetDir . basename($_FILES["file"]["name"]);

        if (isset($_POST['bigPic']) && isset($_POST['name'])) {
	        if($debug) {file_put_contents($log_file, "bigPic posted\n" , FILE_APPEND | LOCK_EX);}
            $id = $_POST['name'];
            $file = $_FILES['file'];
            $extension = strrchr($file['name'], '.');
            if (!is_file($file["tmp_name"])) {
	            if($debug) {file_put_contents($log_file, "pas de fichier uploadé\n" , FILE_APPEND | LOCK_EX);}
                die("pas de fichier uploadé.");
            }
            if (in_array($extension, $typeAccepted)) {
	            if($debug) {file_put_contents($log_file, "fichier accepté\n" , FILE_APPEND | LOCK_EX);}
                if (move_uploaded_file($file["tmp_name"], $targetFilePath)) {
		            if($debug) {file_put_contents($log_file, "fichier copie dans temp : ".$targetFilePath."\n" , FILE_APPEND | LOCK_EX);}
                    // Getting the dimensions of the pictures
                    $dimensions = $this->getDimensions($targetFilePath)["dimensions"];
                    $width = $dimensions[0];
                    $height = $dimensions[1];
                    $mimeType = $this->getDimensions($targetFilePath)["mimetype"];

                    $obj = new ca_objects($id);
                    $obj->setMode(ACCESS_WRITE);
                    $resizedFilePath=null;

                    // if the file is too big
                    if ($width > 6000 || $height > 6000) {
                        $resize_on = ($width > $height ? "width" : "height");
			            if($debug) {file_put_contents($log_file, "dimension supérieure à 6000 : ".$width."x".$height." (".$resize_on.") \n" , FILE_APPEND | LOCK_EX);}
                        $original_filename = $targetFilePath;
                        $path_parts = pathinfo($targetFilePath);
                        $resizedFilePath = $path_parts['dirname'] . DIRECTORY_SEPARATOR . $path_parts['filename'] . "_resized.jpg";
                        $command = "convert -resize 6000 " . caEscapeShellArg($targetFilePath) . " " . caEscapeShellArg($resizedFilePath);
			            if($debug) {file_put_contents($log_file, "resize command : \n" , FILE_APPEND | LOCK_EX);}
			            if($debug) {file_put_contents($log_file, $command."\n" , FILE_APPEND | LOCK_EX);}
			            
			            exec($command, $output);
			            if($debug) {file_put_contents($log_file, $output."\n" , FILE_APPEND | LOCK_EX);}
                        if ($output != array()) {
                            die("probleme lors du redimensionnement.");
                        }
                        $targetFilePath = $resizedFilePath;
                        $this->notification->addNotification(_t("Big picture treated, it has been downgraded to 6000px on upload."), __NOTIFICATION_TYPE_INFO__);
                        //['_uploaded_file'] ['tmp_name']

			            if($debug) {file_put_contents($log_file, "ajout valeur bigPicture_originals\n" , FILE_APPEND | LOCK_EX);}
			            
                        $obj->addAttribute(array(
                            'locale_id' => 2,
                            'bigPicture_originals' => $original_filename
                        ), 'bigPicture_originals');
                        if ($obj->numErrors()) {
                            print "ERROR ADDING bigPicture_originals TO OBJECT {$id}: ".join('; ', $obj->getErrors())."\n";
                            return false;
                        }
                        $obj->update();

			            if($debug) {file_put_contents($log_file, "ajout valeur bigPicture_originals\n" , FILE_APPEND | LOCK_EX);}

                    }
                    //Getting the representation id of the object
                    /*                        $vt_rep = new ca_object_representations();
                                        $vt_rep->setMode(ACCESS_WRITE);
                                        $vt_rep->set("media", $targetFilePath);
                                        $vt_rep->set("type_id", "type_big_picture");
                                        $vt_rep->insert();

                                        $rep_id     = $vt_rep->get("representation_id");
                                        $type_id    = $vt_rep->get("type_id");
                                        $status     = $vt_rep->get("status");
                                        $access     = $vt_rep->get("access");*/

                    //var_dump($obj->get("ca_objects.preferred_labels.name"));
                    $result = $obj->addRepresentation($targetFilePath, 132, 2, 4, 2, 1);
                    $obj->update();
                    if (!$result) {
                        //Probleme de type probablement
                        var_dump($result);
                        var_dump($obj->getRepresentationCount());
                        var_dump($obj->getRepresentationIDs());
                        die();
                    }
                    if ($obj->numErrors() > 0) {
                        var_dump($obj->getErrors());
                        die();
                    }
                    // Treatment files deletion
                    unlink($targetFilePath);
                    if(is_file($original_filename)) {
                        unlink($original_filename);
                    }
                } else {
                    echo "Sorry, there was an error uploading your file.\n";
                }
                // Redirection vers l'affichage de l'objet si tout est ok
                $this->getResponse()->setRedirect(__CA_URL_ROOT__ . "/index.php/editor/objects/ObjectEditor/Edit/object_id/" . $id);
            } else {
                die("Erreur : Problème d'upload, veuillez vérifier la configuration serveur.");
            }
        } else {
            die("no data posted");
        }
    }
}

?>
