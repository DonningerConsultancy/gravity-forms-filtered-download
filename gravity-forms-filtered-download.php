<?php
/*
Plugin Name: Gravity Forms Filtered Entries Download
Plugin URI:  http://donninger.nl
Description: Download Gravity Forms entries based on a predefined filter
             To perform a backup, add cronjob for: https://drugsincidenten.nl?gfbackup=true
Version:     1.1
Author:      Donninger Consultancy
Author URI:  http://donninger.nl
Text Domain: gravity-forms
Domain Path: /lang
 */
 add_shortcode( 'blablabla', 'doBlablabla' );

 function doBlablabla() {
   return "Shortcode";
 }

if (class_exists("GFForms")) {
  GFForms::include_addon_framework();

  class GFFilteredDownload extends GFAddOn {

    protected $_version = "1.1";
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
    private $total_count;

    public function init() {
      parent::init();

      include_once("config.inc.php");
      $this->delimiter = $config["delimiter"];
      $this->newline = $config["newline"];
      $this->filterKey = $config["filterKey"];
      $this->formId = $config["formId"];
      $this->limit = $config["limit"];

      $upload_dir = wp_upload_dir();
      $this->uploadDir = $upload_dir["basedir"] . "/csv/";
      $this->uploadUrl = $upload_dir["baseurl"] . "/csv/";

      if(isset($_GET["submit"])) {
          $this->handleSubmit($_GET["submit"]);
      } else {
        add_shortcode( 'download-filtered-entries', array('GFFilteredDownload','handleShortCode') );
      }

  	  if(isset($_GET["gfbackup"])) {
  	    $this->removeYesterdaysBackup();
  	    $this->backup($_GET["offset"], $_GET["records"]);
  	    exit();
  	  }
    }

    public function plugin_page() {
  	  $url = $this->uploadUrl . "download_" . md5(date('d-m-Y')) . ".csv";
  	  echo "<a href=\"$url\">Download today's backup</a><br/>\n";
      // include("settingsPage.inc.php");
      echo $this->handleShortCode(0);
    }

    public function handleShortcode($atts) {
      $total_count = $this->getTotalCount();
      $nr_pages = ceil($total_count / $this->limit );

      /**
      ** Show download button(s)
      **/
      $returnValue = "<p>Totaal aantal records: $total_count</p>";
      $returnValue .= "<p><strong>Download batch:</strong></p>\n";
      $returnValue .= "<form>";
      for($i = 1; $i <= $nr_pages; $i++) {
        $returnValue .= "<input type=\"submit\" name=\"submit\" value=\"$i\">\n";
      }
      $returnValue .= "</form>";
      return $returnValue;
    }

    private function handleSubmit($page) {
      //collect connected companies
      $user = wp_get_current_user();
      $instellingen = di_usersInstituteAsJsArray($user->ID);
	    $offset = $page*$this->limit;
      $entries = $this->getEntries($offset, $this->limit, $instellingen);
  	  $array = $this->entries2Array($entries, true);
  	  $csv = $this->array2Csv($array);
  	  $filename = "download_" . date('d-m-Y') . "--" . $_GET["submit"] . ".csv";
  	  $filetype = "application/csv";
  	  $this->forceDownload($csv, $filetype, $filename);
  	}

  	private function backup($offset, $records) {
  	  // $this->log("backing up...\n");
  	  $entries = $this->getEntries($offset, $records, []);
  	  // $this->log("nr of entries: " . sizeof($entries));
  	  //if we are continuing the appending of a file, skip the header rows
  	  $showHeaders = $offset > 0 ? false : true;
  	  $array = $this->entries2Array($entries, $showHeaders);
  	  // $this->log("array size: " . sizeof($array));
  	  $csv = $this->array2Csv($array);
  	  $filename = $this->uploadDir . "download_" . md5(date('d-m-Y')) . ".csv";
  	  // $this->log("appending " . sizeof($array) . " records to file $filename starting from record $offset");
  	  $this->appendToFile($csv, $filename);
  	}

  	private function removeYesterdaysBackup() {
  	  $date = new DateTime();
  	  $date->add(DateInterval::createFromDateString('yesterday'));
  	  //echo "yesterday: " . $date->format('d-m-Y') . "\n";
  	  //echo "yesterday hash: " . md5($date->format('d-m-Y')) . "\n";
  	  $filename = $this->uploadDir . "download_" . md5($date->format('d-m-Y')) . ".csv";
  	  // $this->log("removing file $filename");
  	  unlink($filename);
      // $this->log("file removed!");
  	}

    private function getTotalCount() {
      // echo "<hr/><hr/>getTotalCount<hr/>";
      $entries = $this->getEntries(0, 1, []);
      return $this->total_count;
    }

    private function getEntries($offset, $limit, $instellingen) {
      //set default limit
  	  if(!$limit) { $limit = $this->limit; }
  	  //set default offset
  	  if(!$offset) { $offset = 0; }

  	  if(count($instellingen)>0) {
  	    // $search_criteria['field_filters'][] = array('key' => $this->filterKey, 'operator' => 'in', 'value' => explode(",", str_replace("'","",$instellingen)) );
  	  } else {
        $search_criteria = array();
      }

      $sorting = array( 'key' => "4", 'direction' => "ASC" );
      $paging = array('offset' => $offset, 'page_size' => $limit );
      $total_count = 0;
      //collect entries based on filter
      // $this->log("Getting GF entries... $this->formId, $search_criteria, $sorting, $paging, $total_count ");
      $entries = GFAPI::get_entries( $this->formId, $search_criteria, $sorting, $paging, $total_count );
      // $this->log("Found $total_count entries");
      $this->total_count = $total_count;
      return $entries;
    }

	  private function entries2Array($entries, $showHeaders) {
      //this will be the output array:
      $output = array();

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

  	  //start output with ID of the entry
  	  $output[0][] = "--";
  	  $output[1][] = "ID";

  	  //put field id's and names in first two rows of the output
  	  foreach($fieldsArray as $fieldKey => $fieldName) {
  	    $output[0][] = "$fieldKey";
  	    $output[1][] = $fieldName;
  	  }

  	  //remove empty value from array
  	  $ids = $output[0];
  	  $dummy = array_shift($ids);
  	  unset($dummy);

      //store entries in the output array
      foreach($entries as $entry) {
        $outputRecord = array();
        //insert the entry id in front of the record
        $outputRecord[] = rgar( $entry, 'id' );
        //store the values in the correct column
        foreach($ids as $fieldKey) {
          $outputRecord[] = $entry[$fieldKey];
        }
        //add record to output
        $output[] = $outputRecord;
      }

  	  if(!$showHeaders) {
  	    //remove the two header rows
  	    $dummy = array_shift($output);
  	    $dummy = array_shift($output);
  	  }
	    return $output;
      /*
      echo "<pre>";
      echo "\nBATCH/PAGE:\n";
      echo $_GET["submit"];
      echo "\nNR ENTRIES:\n";
      echo $this->total_count;
      echo "\nENTRIES:\n";
      var_dump($entries);
      echo "FIELDS:\n";
      var_dump($fields);
      echo "FIELDS ARRAY:\n";
      var_dump($fieldsArray);
      echo "OUTPUT ARRAY:\n";
      var_dump($output);
      echo "</pre>";
      exit();
*/
    }


	  private function forceDownload($contents, $filetype, $filename) {
      //output to downloadable file:
      header("Content-Type: $filetype; charset=UTF-8");
      header("Content-Disposition: attachment; filename=$filename");
      header("Pragma: no-cache");
      echo $contents;
      exit();
    }

  	private function appendToFile($contents, $filename) {
  	  $fp = fopen($filename, "a");
  	  fwrite($fp, $contents);
  	  fclose($fp);
  	}

    private function array2Csv($array) {
      //build the CSV output from the output array
      $output = "";
      foreach($array as $record) {
        $fieldCounter = 0;
        foreach($record as $field) {
          if( $fieldCounter != 0) { $output.=$this->delimiter; }
          //remove endline characters because this messes up files in MsExcel
          $field = str_replace("\r\n"," | ",$field);
          $field = str_replace("\n"," | ",$field);
          $field = str_replace("\r"," | ",$field);
          $output.="\"" . $field . "\"";
          $fieldCounter++;
        }
        $output.=$this->newline;
      }
      return $output;
    }

    //log function that uses default output. Redirect with bash script if needed
    private function log($string) {
      echo strftime("%c") . " - $string\n";
    }
  } //end class
    new GFFilteredDownload();
}
