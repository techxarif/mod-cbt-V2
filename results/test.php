<?php
// results/test.php — View Test Results
require_once __DIR__ . '/../includes/bootstrap.php';
requireTeacher();

$test_id = int_param('test_id', 0) ?: int_param('id', 0);
redirect("index.php?test_id=$test_id");
