<?php

class IndexFixerGSC {
    public function update_url_status($url, $status, $last_checked) {
        // Implementation of update_url_status method
    }

    public function check_url($url) {
        // Implementation of check_url method
    }

    public function get_response($url) {
        // Implementation of get_response method
    }

    public function process_url($url) {
        // Implementation of process_url method
    }

    public function update_url_status_if_not_empty($url, $response) {
        if (isset($response['inspectionResult']['indexStatusResult']['coverageState']) && 
            !empty($response['inspectionResult']['indexStatusResult']['coverageState'])) {
            $status = $response['inspectionResult']['indexStatusResult']['coverageState'];
            $last_checked = current_time('mysql');
            $this->update_url_status($url, $status, $last_checked);
        }
    }
} 