<?php
define('CLI_SCRIPT', true);
require_once('/var/www/html/moodlecourse/config.php');

$quiz = $DB->get_record('quiz', ['id' => 1]);
$course = $DB->get_record('course', ['id' => $quiz->course]);
$cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id);
$quiz_context = context_module::instance($cm->id);

echo "Quiz context: {$quiz_context->id}\n";

// Get quiz question category
$qcat = $DB->get_record('question_categories', ['contextid' => $quiz_context->id, 'parent' => 1]);
if (!$qcat) {
    // Create it
    $topcat = $DB->get_record('question_categories', ['contextid' => $quiz_context->id, 'parent' => 0]);
    if (!$topcat) {
        echo "No top category found\n";
        exit(1);
    }
    $qcat = new stdClass();
    $qcat->name = 'Default for CD511 Quiz';
    $qcat->contextid = $quiz_context->id;
    $qcat->info = '';
    $qcat->infoformat = 1;
    $qcat->stamp = make_unique_id_code();
    $qcat->parent = $topcat->id;
    $qcat->sortorder = 999;
    $qcat->id = $DB->insert_record('question_categories', $qcat);
    echo "Created quiz category: {$qcat->id}\n";
} else {
    echo "Found quiz category: {$qcat->id} ({$qcat->name})\n";
}

$admin = get_admin();

// Get all questions
$questions = $DB->get_records('question', [], 'id ASC');
echo "Total questions: " . count($questions) . "\n";

// Delete any old bank entries/versions for this category
$old_entries = $DB->get_records('question_bank_entries', ['questioncategoryid' => $qcat->id]);
foreach ($old_entries as $e) {
    $DB->delete_records('question_versions', ['questionbankentryid' => $e->id]);
    $DB->delete_records('question_bank_entries', ['id' => $e->id]);
}
echo "Cleaned old entries\n";

// Create bank entries and versions for each question
$bank_entries = [];
foreach ($questions as $q) {
    $entry = new stdClass();
    $entry->questioncategoryid = $qcat->id;
    $entry->idnumber = null;
    $entry->ownerid = $admin->id;
    $entry->nextversion = 2;
    $entry->id = $DB->insert_record('question_bank_entries', $entry);

    $version = new stdClass();
    $version->questionbankentryid = $entry->id;
    $version->version = 1;
    $version->questionid = $q->id;
    $version->status = 'ready';
    $version->id = $DB->insert_record('question_versions', $version);

    $bank_entries[] = $entry;
    echo "Question {$q->id} -> entry {$entry->id} -> version {$version->id}\n";
}

// Now link slots to bank entries
$slots = $DB->get_records('quiz_slots', ['quizid' => $quiz->id], 'slot ASC');
$slot_array = array_values($slots);

// Delete old references
$DB->delete_records('question_references', ['usingcontextid' => $quiz_context->id, 'component' => 'mod_quiz', 'questionarea' => 'slot']);

for ($i = 0; $i < min(count($slot_array), count($bank_entries)); $i++) {
    $slot = $slot_array[$i];
    $entry = $bank_entries[$i];

    $ref = new stdClass();
    $ref->usingcontextid = $quiz_context->id;
    $ref->component = 'mod_quiz';
    $ref->questionarea = 'slot';
    $ref->itemid = $slot->id;
    $ref->questionbankentryid = $entry->id;
    $ref->version = null;
    $DB->insert_record('question_references', $ref);
    echo "Linked slot {$slot->slot} to entry {$entry->id}\n";
}

echo "=== DONE ===\n";
echo "Reload the quiz Questions page now.\n";
