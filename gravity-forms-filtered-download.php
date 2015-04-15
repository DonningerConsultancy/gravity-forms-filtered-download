<?php
/*
Plugin Name: Gravity Forms Filtered Entries Download
Plugin URI:  http://donninger.nl
Description: Download Gravity Forms entries based on a predefined filter
Version:     0.1-alpha
Author:      Donninger Consultancy
Author URI:  http://donninger.nl
Text Domain: gravity-forms
Domain Path: /lang
 */
if (class_exists("GFForms")) {
    GFForms::include_addon_framework();

    class GFFilteredDownload extends GFAddOn {

        protected $_version = "1.0";
        protected $_min_gravityforms_version = "1.7.9999";
        protected $_slug = "gravity-forms-filtered-download";
        protected $_path = "gravity-forms-filtered-download/gravity-forms-filtered-download.php";
        protected $_full_path = __FILE__;
        protected $_url = "http://www.donninger.nl";
        protected $_title = "Gravity Forms Filtered Entries Downloader";
        protected $_short_title = "Filtered download";

        //CSV options
        private $delimiter;
        private $newline;

        private $filterKey;
        private $formId;

        public function init() {
          parent::init();

          include_once("config.inc.php");
          $this->delimiter = $config["delimiter"];
          $this->newline = $config["newline"];
          $this->filterKey = $config["filterKey"];
          $this->formId = $config["formId"];

          if(isset($_GET["submit"])) {
              $this->handleSubmit();
          } else {
            add_shortcode( 'download-filtered-entries', array(&$this,'handleShortCode') );
          }
        }

        public function plugin_page() {
            include("settingsPage.inc.php");
        }

        public function form_settings_fields($form) {
            return array(
                array(
                    "title"  => "Simple Form Settings",
                    "fields" => array(
                        array(
                            "label"   => "My checkbox",
                            "type"    => "checkbox",
                            "name"    => "enabled",
                            "tooltip" => "This is the tooltip",
                            "choices" => array(
                                array(
                                    "label" => "Enabled",
                                    "name"  => "enabled"
                                )
                            )
                        )
                    )
                )
            );
        }

        public function plugin_settings_fields() {
            return array(
                array(
                    "title"  => "Simple Add-On Settings",
                    "fields" => array(
                        array(
                            "name"    => "textbox",
                            "tooltip" => "This is the tooltip",
                            "label"   => "This is the label",
                            "type"    => "text",
                            "class"   => "small"
                        )
                    )
                )
            );
        }

        public function handleShortcode($atts) {
          $returnValue = "<form>\n";
          $returnValue .= "<input type=\"submit\" name=\"submit\" value=\"download\">\n";
          $returnValue .= "</form>\n";
          return $returnValue;

        }

        private function handleSubmit() {

          //this will be the output array:
          $output = array();

          //collect connected companies
          $user = wp_get_current_user();
          $instellingen = di_usersInstituteAsJsArray($user->ID);

          //prepare form
          $form = GFAPI::get_form($this->formId);

          //prepare fields to use fieldnames as CSV headers
          $fieldsArray = array();
          $fields = $form["fields"];
          foreach($fields as $field) {
            //collect checkboxes separately
            if($field->type == "checkbox") {
              foreach($field["inputs"] as $option) {
                $fieldsArray[$option["id"]] = $option["label"];
              }
            }
            if($field->adminLabel != "") {
              $fieldsArray[$field->id] = $field->adminLabel;
            }
          }

          //put field id's and names in first two rows of the output
          foreach($fieldsArray as $fieldKey => $fieldName) {
            $output[0][] = "$fieldKey";
            $output[1][] = $fieldName;
          }

          $search_criteria['field_filters'][] = array('key' => $this->filterKey, 'operator' => 'in', 'value' => explode(",", str_replace("'","",$instellingen)) );
          //$search_criteria['field_filters'][] = array('key' => '1', 'operator' => 'not in', value' => array( 'Alex', 'David', 'Dana' );

          //collect entries based on filter
          $entries = GFAPI::get_entries($this->formId, $search_criteria);

          //store entries in the output array
          foreach($entries as $entry) {
            $outputRecord = array();

            //store the values in the correct column
            foreach($output[0] as $fieldKey) {
              $outputRecord[] = $entry[$fieldKey];
            }

            //add record to output
            $output[] = $outputRecord;
          }


          //echo "<pre>";
          //echo "\nENTRIES:\n";
          //var_dump($entries);
          //echo "FIELDS:\n";
          //var_dump($fields);
          //echo "FIELDS ARRAY:\n";
          //var_dump($fieldsArray);
          //echo "OUTPUT ARRAY:\n";
          //var_dump($output);
          //echo "</pre>";


          //output to downloadable file:
          header('Content-Type: application/csv');
          header('Content-Disposition: attachment; filename=download_' . date('d-m-Y') . '.csv');
          header('Pragma: no-cache');
          echo $this->array2Csv($output);
          exit();
        }

        private function array2Csv($array) {
          //build the CSV output from the output array
          $output = "";
          foreach($array as $record) {
            foreach($record as $field) {
              $output.="\"$field\"";
              $output.=$this->delimiter;
            }
            $output.=$this->newline;
          }
          return $output;
        }
    } //end class

    new GFFilteredDownload();
}
