<?php

namespace App\Plugin;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class PluginVerifyChecksum extends AbstractVerifyChecksumPlugin {

    public function execute($resource_id, $comparison_checksum){

        if (isset($this->settings['drupal_baseurl'])) {
            $this->drupal_base_url = $this->settings['drupal_baseurl'];
        } else {
            $this->drupal_base_url = 'http://drupalvm.test';
        }
        if (isset($this->settings['fixity_algorithm'])) {
            $this->fixity_algorithm = $this->settings['fixity_algorithm'];
        } else {
            $this->fixity_algorithm = 'sha1';
        }
        if (isset($this->settings['drupal_user'])) {
            $this->drupal_user = $this->settings['drupal_user'];
        } else {
            $this->drupal_user = 'admin';
        }
        if (isset($this->settings['drupal_password'])) {
            $this->drupal_password = $this->settings['drupal_password'];
        } else {
            $this->drupal_password = 'admin';
        }

        $fits_checksum = $this->getFileFitsChecksumFromDrupal($resource_id);
        if(strlen($fits_checksum)){
            return strcmp($fits_checksum, $comparison_checksum) == 0;
        }
        return false;

    }


    /**
    * Get FITS Checksum value for a File entity from Drupal.
    *
    * @param string $resource_id
    *    This file's Drupal (filesystem) URI.
    *
    * @return string
    *    The FITS Checksum value of the file, or false. Used for comparsion
    *    of the new checksum value with the value that Riprap is using.
    */
    private function getFileFitsChecksumFromDrupal($resource_id)
    {
        // Retrieve the media entity from Drupal.
        try {
            $get_digest_url = $this->drupal_base_url . '/islandora_riprap/checksum?file_uri=' .
            $resource_id . '&algorithm=' . $this->fixity_algorithm;

            $client = new \GuzzleHttp\Client();
            $response = $client->request('GET', $get_digest_url, [
                'http_errors' => false,
                'auth' => [$this->drupal_user, $this->drupal_password]
            ]);
            $status_code = $response->getStatusCode();
            $allowed_codes = array(200);
            if (in_array($status_code, $allowed_codes)) {
                $response_body = json_decode($response->getBody(), true);
                $file_uuid = $response_body[0]['file_uuid'];

                $file_client = new \GuzzleHttp\Client();
                $get_fits_url = $this->drupal_base_url  . '/file_export/' . $file_uuid;
                $fits_response = $file_client->request('GET', $get_fits_url, [
                    'http_errors' => false,
                    'auth' => [$this->drupal_user, $this->drupal_password]
                ]);
                $fits_status_code = $fits_response->getStatusCode();
                if(in_array($fits_status_code, $allowed_codes)){
                    $fits_body = json_decode($fits_response->getBody(), true);
                    // print_r($fits_body);
                    $fits_checksum = $fits_body[0]["field_fits_checksum"];
                    return $fits_checksum;
                }

            } 
            // If the HTTP status code is not in the allowed list, log it.
            $this->logger->warning("getFileFitsCheckSum cannot retrieve FITS checksum from Drupal.", array(
                'resource_id' => $url,
                    'status_code' => $status_code,
                    'algorithm' => $algorithm,
                    'resource_id' => $resource_id,
                ));
                return false;
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error(
                    "PluginVerifyChecksum could not get File FITS checksum from Drupal.",
                    array(
                        'HTTP response code' => $code,
                        'Exception message' => $e->getMessage()
                    )
                );
            }
            return false;
        }
    }
}