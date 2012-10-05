<?php

require_once("../../config.php");
require_once($CFG->dirroot.'/mod/forum/lib.php');   // We'll need this

// Check login
require_login();

$PAGE->set_url('/theme/mymobile/news.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');

$PAGE->set_heading('Courses');


echo $OUTPUT->header();

echo $OUTPUT->heading('My News', 2, 'headingblock header');

$courses  = enrol_get_my_courses('summary', 'visible DESC,sortorder ASC');

if (!empty($courses)) {
	foreach ($courses as $course) {
		if ($course->id == SITEID) {
			continue;
		}

        if (!$forum = forum_get_course_forum($course->id, 'news')) {
            exit;
        }

		$modinfo = get_fast_modinfo($course);
        $cm = $modinfo->instances['forum'][$forum->id];

		if (! $discussions = forum_get_discussions($cm, 'p.modified DESC', false,
												   -1, 5) ) {
			continue;
		}
		
	/// Actually create the listing now

		$strftimerecent = get_string('strftimerecent');
		$strmore = get_string('more', 'forum');

	/// Accessibility: markup as a list.
		echo '<div class="headingwrap ui-bar-b ui-footer" style="margin: 20px 0 10px 0"><h2 class="main ui-title">'.$course->fullname.'</h2></div>';

		echo '<ul data-role="controlgroup">';
		foreach ($discussions as $discussion) {

			$discussion->subject = $discussion->name;

			$discussion->subject = format_string($discussion->subject, true, $forum->course);

		echo '<li><div class="coursebox clearfix"><div class="info">';
		echo '<a href="'.$CFG->wwwroot.'/mod/forum/discuss.php?d='.$discussion->discussion.'" data-role="button" data-icon="arrow-r" data-iconpos="right" data-theme="d" style="text-align: left;">';
		echo '<span style="color: #555; font-size: 14px;">'.userdate($discussion->modified, $strftimerecent).' / </span>'.$discussion->subject;
		echo '</a></div></div></li>';

		/*
		$text .= '<li class="post">'.
					 '<div class="head clearfix">'.
					 '<div class="date">'.userdate($discussion->modified, $strftimerecent).'</div>'.
					 '<div class="name">'.fullname($discussion).'</div></div>'.
					 '<div class="info">'.$discussion->subject.' '.
					 '<a href="'.$CFG->wwwroot.'/mod/forum/discuss.php?d='.$discussion->discussion.'">'.
					 $strmore.'...</a></div>'.
					 "</li>\n";
		*/
		}
		
		echo "</ul>\n";
	}
}

echo $OUTPUT->footer();
