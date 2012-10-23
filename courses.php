<?php

require_once("../../config.php");

// Check login
require_login();

$PAGE->set_url('/theme/quoodle/courses.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');

$PAGE->set_heading('Courses');


echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('mycourses'), 2, 'headingblock header');
print_my_moodle();

echo $OUTPUT->footer();
