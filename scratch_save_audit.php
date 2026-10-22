<?php
$data = file_get_contents('php://input');
if ($data) {
    file_put_contents('scratch_audit_results.json', $data);
    echo "Saved";
}
