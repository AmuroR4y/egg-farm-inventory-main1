<?php
file_put_contents('scratch_right_probe.json', file_get_contents('php://input'));
echo "Saved";
