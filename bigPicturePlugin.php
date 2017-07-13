<?php
/* ----------------------------------------------------------------------
 * mediaImportPlugin.php :
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

class bigPicturePlugin extends BaseApplicationPlugin {
    # -------------------------------------------------------
    protected $description = 'Utility plugin to resize big picture';
    # -------------------------------------------------------
    private $opo_config;
    private $ops_plugin_path;
    # -------------------------------------------------------

    public function __construct($ps_plugin_path) {
        $this->ops_plugin_path = $ps_plugin_path;
        $this->description = _t('Resize big tiff picture');
        parent::__construct();
        $this->opo_config = Configuration::load($ps_plugin_path.'/conf/bigPicture.conf');
    }

    # -------------------------------------------------------
    /**
     * Override checkStatus() to return true - the statisticsViewerPlugin always initializes ok... (part to complete)
     */
    public function checkStatus() {
        return array(
            'description' => $this->getDescription(),
            'errors' => array(),
            'warnings' => array(),
            'available' => ((bool)$this->opo_config->get('enabled'))
        );
    }

    # -------------------------------------------------------
    /**
     * Insert into ObjectEditor info (side bar)
     */
    public function hookAppendToEditorInspector(array $va_params = array())
    {
        $t_item = $va_params["t_item"];
        $vn_item_id = $t_item->getPrimaryKey();

        if (!isset($t_item)) return false;

        // fetching content of already filled vs_buf_append to surcharge if present (cumulative plugins)
        if (isset($va_params["vs_buf_append"])) {
            $vs_buf = $va_params["vs_buf_append"];
        } else {
            $vs_buf = "";
        }

        $vn_item_id = $t_item->getPrimaryKey();

        $url =  $this->getRequest()->getFullUrlPath();
        $path = parse_url($url, PHP_URL_PATH);
        $segments = explode('/', rtrim($path, '/'));
        $id = end($segments);

        $pathToUpload = __CA_URL_ROOT__.'/index.php/bigPicture/Pictures/Upload';

        $va_params["caEditorInspectorAppend"] =
            '<form style="padding:4px;margin:10px 4px;color:white;background:#1CB5C9;border-radius:4px;display:block;" 
            method="POST" action="'.$pathToUpload.'" enctype="multipart/form-data">
            <h3 style="background: transparent;color:white;padding:0;display:block;margin:4px 2px;">Ajout de grandes images</h3>
            <input type="hidden" name="name" value="'.$id.'">
                <input name="file" type="file" />
            <div>
                <input type="submit" name="bigPic" value="OK">
            </div>
            </form>';



        return $va_params;
    }

    # -------------------------------------------------------
    /**
     * Add plugin user actions
     */
    static function getRoleActionList() {
        return array(
            'can_use_statistics_viewer_plugin' => array(
                'label' => _t('Can use statistics viewer functions'),
                'description' => _t('User can use all statistics viewer plugin functionality.')
            )
        );
    }

}
?>