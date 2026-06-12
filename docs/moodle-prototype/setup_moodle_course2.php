<?php
define('CLI_SCRIPT', true);
require_once('/var/www/html/moodlecourse/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');
require_once($CFG->dirroot . '/enrol/manual/lib.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->dirroot . '/lib/questionlib.php');
require_once($CFG->dirroot . '/mod/forum/lib.php');

// Create student users
$students = [];
$student_names = [
    ['firstname' => 'Alice', 'lastname' => 'Smith', 'email' => 'alice.smith@student.edu'],
    ['firstname' => 'Bob', 'lastname' => 'Johnson', 'email' => 'bob.johnson@student.edu'],
    ['firstname' => 'Carol', 'lastname' => 'Williams', 'email' => 'carol.williams@student.edu'],
    ['firstname' => 'David', 'lastname' => 'Brown', 'email' => 'david.brown@student.edu'],
    ['firstname' => 'Eve', 'lastname' => 'Davis', 'email' => 'eve.davis@student.edu'],
];

foreach ($student_names as $idx => $s) {
    $user = new stdClass();
    $user->username = 'student' . ($idx + 1);
    $user->password = 'StudentPass123!';
    $user->firstname = $s['firstname'];
    $user->lastname = $s['lastname'];
    $user->email = $s['email'];
    $user->auth = 'manual';
    $user->confirmed = 1;
    $user->mnethostid = $CFG->mnet_localhost_id;
    $user->id = user_create_user($user);
    $students[] = $user;
    echo "Created user: {$user->username} (ID: {$user->id})\n";
}

// Create course
$course = new stdClass();
$course->fullname = 'Introduction to Student-Centred Teaching';
$course->shortname = 'CD511-Course';
$course->summary = 'This course explores the principles and practices of student-centred teaching, focusing on the Moodle Learning Management System and flipped classroom methodologies. Participants will learn to design engaging online courses, create effective assessments, and facilitate active learning environments.';
$course->summaryformat = FORMAT_HTML;
$course->category = 1;
$course->format = 'topics';
$course->numsections = 3;
$course->startdate = time();
$course->visible = 1;
$course->id = create_course($course)->id;
echo "Created course: {$course->fullname} (ID: {$course->id})\n";

// Update course sections with names
$sections = [1 => 'Module 1: Introduction to LMS', 2 => 'Module 2: Flipped Classroom Design', 3 => 'Module 3: Assessment Strategies'];
foreach ($sections as $sectionnum => $name) {
    $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $sectionnum]);
    if ($section) {
        $section->name = $name;
        $section->summary = "Content for {$name}";
        $section->summaryformat = FORMAT_HTML;
        $DB->update_record('course_sections', $section);
        echo "Updated section {$sectionnum}: {$name}\n";
    }
}

// Enrol students in the course
$manual = enrol_get_plugin('manual');
$instances = enrol_get_instances($course->id, false);
$manual_instance = null;
foreach ($instances as $instance) {
    if ($instance->enrol === 'manual') {
        $manual_instance = $instance;
        break;
    }
}

if (!$manual_instance) {
    $manual_instance = new stdClass();
    $manual_instance->enrol = 'manual';
    $manual_instance->courseid = $course->id;
    $manual_instance->status = ENROL_INSTANCE_ENABLED;
    $manual_instance->id = $manual->add_instance($course);
}

$student_role = $DB->get_record('role', ['shortname' => 'student']);
foreach ($students as $student) {
    $manual->enrol_user($manual_instance, $student->id, $student_role->id);
    echo "Enrolled student: {$student->username}\n";
}

// Add URL resource to Module 1
$url = new stdClass();
$url->course = $course->id;
$url->name = 'Moodle Documentation';
$url->intro = 'Official Moodle documentation for reference.';
$url->externalurl = 'https://docs.moodle.org';
$url->section = 1;
$url->module = $DB->get_field('modules', 'id', ['name' => 'url']);
$url->modulename = 'url';
$url->visible = 1;
$url->id = add_moduleinfo($url, $course);
echo "Added URL resource: {$url->name}\n";

// Add a Page resource to Module 2
$page = new stdClass();
$page->course = $course->id;
$page->name = 'Flipped Classroom Guide';
$page->intro = 'A comprehensive guide to designing flipped classroom experiences.';
$page->content = '<p>Flipped classroom is an instructional strategy that reverses the traditional learning environment by delivering instructional content, often online, outside of the classroom. In-class time is then used for activities, discussions, and hands-on learning.</p><h3>Key Benefits:</h3><ul><li>Students learn at their own pace</li><li>More class time for active learning</li><li>Personalized instruction</li></ul>';
$page->contentformat = FORMAT_HTML;
$page->section = 2;
$page->module = $DB->get_field('modules', 'id', ['name' => 'page']);
$page->modulename = 'page';
$page->visible = 1;
$page->id = add_moduleinfo($page, $course);
echo "Added Page resource: {$page->name}\n";

// Add a File resource to Module 3
$file_resource = new stdClass();
$file_resource->course = $course->id;
$file_resource->name = 'Assessment Design PDF';
$file_resource->intro = 'Downloadable PDF on assessment design principles.';
$file_resource->section = 3;
$file_resource->module = $DB->get_field('modules', 'id', ['name' => 'resource']);
$file_resource->modulename = 'resource';
$file_resource->visible = 1;
$file_resource->id = add_moduleinfo($file_resource, $course);
echo "Added File resource: {$file_resource->name}\n";

// Create a Forum (Announcements)
$forum = new stdClass();
$forum->course = $course->id;
$forum->name = 'Course Announcements';
$forum->intro = 'Important announcements and updates for the course.';
$forum->type = 'news';
$forum->section = 0;
$forum->module = $DB->get_field('modules', 'id', ['name' => 'forum']);
$forum->modulename = 'forum';
$forum->visible = 1;
$forum->id = add_moduleinfo($forum, $course);
echo "Added Forum: {$forum->name}\n";

// Post an announcement
$discussion = new stdClass();
$discussion->course = $course->id;
$discussion->forum = $forum->id;
$discussion->name = 'Welcome to CD511!';
$discussion->message = '<p>Welcome to Introduction to Student-Centred Teaching! This course will guide you through the fundamentals of LMS management and flipped classroom design. Please review the modules and complete all activities before the deadline.</p>';
$discussion->messageformat = FORMAT_HTML;
$discussion->messagetrust = 0;
$discussion->groupid = -1;
$discussion->timestart = 0;
$discussion->timeend = 0;

$admin = get_admin();
$discussion->userid = $admin->id;
$discussion->usermodified = $admin->id;
$discussion->id = forum_add_discussion($discussion, null, null, $admin->id);
echo "Posted announcement: {$discussion->name}\n";

// Create a Quiz in Module 3
$quiz = new stdClass();
$quiz->course = $course->id;
$quiz->name = 'Module 3 Quiz: Assessment Knowledge';
$quiz->intro = 'This quiz assesses your understanding of assessment design principles covered in Module 3.';
$quiz->section = 3;
$quiz->module = $DB->get_field('modules', 'id', ['name' => 'quiz']);
$quiz->modulename = 'quiz';
$quiz->visible = 1;
$quiz->grade = 100;
$quiz->grademethod = 1; // QUIZ_GRADEHIGHEST
$quiz->timelimit = 1800;
$quiz->quizpassword = '';
$quiz->id = add_moduleinfo($quiz, $course);
echo "Created Quiz: {$quiz->name}\n";

// Get question category
$course_context = context_course::instance($course->id);
$qcat = $DB->get_record('question_categories', ['contextid' => $course_context->id]);
if (!$qcat) {
    // Create default category
    $defaultcat = $DB->get_record('question_categories', ['contextid' => $course_context->id, 'parent' => 0]);
    if (!$defaultcat) {
        $defaultcat = new stdClass();
        $defaultcat->contextid = $course_context->id;
        $defaultcat->name = 'Default for CD511-Course';
        $defaultcat->info = '';
        $defaultcat->sortorder = 999;
        $defaultcat->stamp = make_unique_id_code();
        $defaultcat->parent = 0;
        $defaultcat->id = $DB->insert_record('question_categories', $defaultcat);
    }
    $qcat = $defaultcat;
}

// Create quiz questions
$questions = [
    ['name' => 'Q1: LMS Definition', 'questiontext' => 'What does LMS stand for in educational technology?', 'qtype' => 'multichoice', 'answers' => [
        ['text' => 'Learning Management System', 'fraction' => 1],
        ['text' => 'Learning Media Software', 'fraction' => 0],
        ['text' => 'Lesson Management Service', 'fraction' => 0],
        ['text' => 'Local Media Storage', 'fraction' => 0],
    ]],
    ['name' => 'Q2: Flipped Classroom', 'questiontext' => 'In a flipped classroom model, students typically review content at home and engage in activities during class time.', 'qtype' => 'truefalse', 'correct' => true],
    ['name' => 'Q3: Moodle Features', 'questiontext' => 'Which of the following is NOT a standard Moodle activity module?', 'qtype' => 'multichoice', 'answers' => [
        ['text' => 'Quiz', 'fraction' => 0],
        ['text' => 'Forum', 'fraction' => 0],
        ['text' => 'Video Editor', 'fraction' => 1],
        ['text' => 'Assignment', 'fraction' => 0],
    ]],
    ['name' => 'Q4: Assessment Types', 'questiontext' => 'Formative assessment is primarily used to:', 'qtype' => 'multichoice', 'answers' => [
        ['text' => 'Grade students at the end of a course', 'fraction' => 0],
        ['text' => 'Monitor student learning and provide ongoing feedback', 'fraction' => 1],
        ['text' => 'Replace final examinations', 'fraction' => 0],
        ['text' => 'Determine scholarship eligibility', 'fraction' => 0],
    ]],
    ['name' => 'Q5: Active Learning', 'questiontext' => 'Active learning strategies require students to engage with course material through discussion, problem-solving, and reflection.', 'qtype' => 'truefalse', 'correct' => true],
    ['name' => 'Q6: Blended Learning', 'questiontext' => 'Blended learning combines online digital media with traditional classroom methods.', 'qtype' => 'truefalse', 'correct' => true],
    ['name' => 'Q7: Student Engagement', 'questiontext' => 'Which strategy is most effective for increasing student engagement in an online course?', 'qtype' => 'multichoice', 'answers' => [
        ['text' => 'Posting all content as PDF documents only', 'fraction' => 0],
        ['text' => 'Using interactive activities, discussions, and multimedia', 'fraction' => 1],
        ['text' => 'Removing all deadlines to reduce stress', 'fraction' => 0],
        ['text' => 'Limiting communication to email only', 'fraction' => 0],
    ]],
    ['name' => 'Q8: Feedback Importance', 'questiontext' => 'Timely and specific feedback is essential for student improvement and motivation.', 'qtype' => 'truefalse', 'correct' => true],
    ['name' => 'Q9: Collaborative Learning', 'questiontext' => 'Collaborative learning in an LMS can be facilitated through:', 'qtype' => 'multichoice', 'answers' => [
        ['text' => 'Individual essay assignments only', 'fraction' => 0],
        ['text' => 'Forums, wikis, and group projects', 'fraction' => 1],
        ['text' => 'Removing group features', 'fraction' => 0],
        ['text' => 'Disabling messaging', 'fraction' => 0],
    ]],
    ['name' => 'Q10: Course Design', 'questiontext' => 'A well-designed online course should include clear learning objectives, varied activities, and multiple assessment opportunities.', 'qtype' => 'truefalse', 'correct' => true],
];

require_once($CFG->dirroot . '/question/type/multichoice/questiontype.php');
require_once($CFG->dirroot . '/question/type/truefalse/questiontype.php');

$multichoice = new qtype_multichoice();
$truefalse = new qtype_truefalse();

foreach ($questions as $qdata) {
    $question = new stdClass();
    $question->category = $qcat->id;
    $question->name = $qdata['name'];
    $question->questiontext = $qdata['questiontext'];
    $question->questiontextformat = FORMAT_HTML;
    $question->generalfeedback = '';
    $question->generalfeedbackformat = FORMAT_HTML;
    $question->defaultmark = 1;
    $question->penalty = 0.3333333;
    $question->stamp = make_unique_id_code();
    $question->version = make_unique_id_code();
    $question->timecreated = time();
    $question->timemodified = time();
    $question->createdby = $admin->id;
    $question->modifiedby = $admin->id;

    if ($qdata['qtype'] === 'multichoice') {
        $question->qtype = 'multichoice';
        $question->single = 1;
        $question->shuffleanswers = 1;
        $question->answernumbering = 'abc';
        $question->correctfeedback = 'Correct!';
        $question->partiallycorrectfeedback = 'Partially correct.';
        $question->incorrectfeedback = 'Incorrect.';
        $question->id = $multichoice->save_question($question);

        foreach ($qdata['answers'] as $idx => $adata) {
            $answer = new stdClass();
            $answer->question = $question->id;
            $answer->answer = $adata['text'];
            $answer->answerformat = FORMAT_PLAIN;
            $answer->fraction = $adata['fraction'];
            $answer->feedback = '';
            $answer->feedbackformat = FORMAT_HTML;
            $answer->id = $DB->insert_record('question_answers', $answer);
        }
    } else {
        $question->qtype = 'truefalse';
        $question->id = $truefalse->save_question($question);
    }

    // Add question to quiz
    $slot = new stdClass();
    $slot->quizid = $quiz->id;
    $slot->page = 1;
    $slot->requireprevious = 0;
    $slot->questionid = $question->id;
    $slot->slot = $DB->count_records('quiz_slots', ['quizid' => $quiz->id]) + 1;
    $slot->id = $DB->insert_record('quiz_slots', $slot);

    echo "Added question: {$qdata['name']} (Type: {$qdata['qtype']})\n";
}

echo "\n=== SETUP COMPLETE ===\n";
echo "Course URL: http://localhost/moodlecourse/course/view.php?id={$course->id}\n";
echo "Admin login: admin / AdminPass123!\n";
echo "Student logins: student1-5 / StudentPass123!\n";
