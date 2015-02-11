<?php
function xmldb_block_helpmenow_upgrade($oldversion = 0) {
    global $CFG, $USER, $DB;
    $dbman = $DB->get_manager();
    $result = true;

    if ($result && $oldversion < 2012082101) {
    /// Define field notify to be added to block_helpmenow_message
        $table = new xmldb_table('block_helpmenow_message');
        $field = new xmldb_field('notify');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '1', 'message');

    /// Launch add field notify
        $result = $result && $dbman->add_field($table, $field);
    }

    if ($result && $oldversion < 2012082102) {
    /// system messages are no longer using get_admin()->id for userid, but instead null
        $result = $result && $DB->set_field('block_helpmenow_message', 'userid', null, array('userid' => get_admin()->id));
    }

    if ($result && $oldversion < 2012082400) {
    /// Define field last_message to be added to block_helpmenow_session2user
        $table = new xmldb_table('block_helpmenow_session2user');
        $field = new xmldb_field('last_message');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, null, null, null, 'last_refresh');

    /// Launch add field last_message
        $result = $result && $dbman->add_field($table, $field);
    }

    if ($result && $oldversion < 2012091200) {
    /// Define field last_message to be added to block_helpmenow_session
        $table = new xmldb_table('block_helpmenow_session');
        $field = new xmldb_field('last_message');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, null, null, null, 'timecreated');

    /// Launch add field last_message
        $result = $result && $dbman->add_field($table, $field);
    }

    if ($result && $oldversion < 2012091400) {
    /// Changing the default of field last_message on table block_helpmenow_session2user to 0
        $table = new xmldb_table('block_helpmenow_session2user');
        $field = new xmldb_field('last_message');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, null, null, '0', 'last_refresh');

    /// Launch change of default for field last_message
        $result = $result && $dbman->change_field_default($table, $field);

    /// Changing the default of field last_message on table block_helpmenow_session to 0
        $table = new xmldb_table('block_helpmenow_session');
        $field = new xmldb_field('last_message');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, null, null, '0', 'timecreated');

    /// Launch change of default for field last_message
        $result = $result && $dbman->change_field_default($table, $field);
    }

    if ($result && $oldversion < 2012092100) {
    /// Define field lastaccess to be added to block_helpmenow_user
        $table = new xmldb_table('block_helpmenow_user');
        $field = new xmldb_field('lastaccess');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, null, null, null, 'motd');

    /// Launch add field lastaccess
        $result = $result && $dbman->add_field($table, $field);
    }

    if ($result && $oldversion < 2012121900) {
    /// Define field optimistic_last_message to be added to block_helpmenow_session2user
        $table = new xmldb_table('block_helpmenow_session2user');
        $field = new xmldb_field('optimistic_last_message');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, null, null, null, 'last_message');

    /// Launch add field optimistic_last_message
        $result = $result && $dbman->add_field($table, $field);

    /// Define field cache to be added to block_helpmenow_session2user
        $table = new xmldb_table('block_helpmenow_session2user');
        $field = new xmldb_field('cache');
        $field->set_attributes(XMLDB_TYPE_TEXT, 'big', null, null, null, null, 'optimistic_last_message');

    /// Launch add field cache
        $result = $result && $dbman->add_field($table, $field);
    }

    if ($result && $oldversion < 2012122700) {

    /// Define table block_helpmenow_error_log to be created
        $table = new xmldb_table('block_helpmenow_error_log');

    /// Adding fields to table block_helpmenow_error_log
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('error', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('details', XMLDB_TYPE_TEXT, 'big', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);

    /// Adding keys to table block_helpmenow_error_log
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

    /// Launch create table for block_helpmenow_error_log
        $result = $result && $dbman->create_table($table);
    }

    if ($result && $oldversion < 2012123100) {

    /// Define field userid to be added to block_helpmenow_error_log
        $table = new xmldb_table('block_helpmenow_error_log');
        $field = new xmldb_field('userid');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, null, null, null, 'timecreated');

    /// Launch add field userid
        $result = $result && $dbman->add_field($table, $field);
    }

    if ($result && $oldversion < 2013011800) {

    /// Define table block_helpmenow_contact to be created
        $table = new xmldb_table('block_helpmenow_contact');

    /// Adding fields to table block_helpmenow_contact
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('contact_userid', XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);

    /// Adding keys to table block_helpmenow_contact
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

    /// Adding indexes to table block_helpmenow_contact
        $table->add_index('block_helpmenow_contact_userid_ix', XMLDB_INDEX_NOTUNIQUE, array('userid'));

    /// Launch create table for block_helpmenow_contact
        $result = $result && $dbman->create_table($table);
    }

    if ($result && $oldversion < 2013050200) {

        /// Define index block_helpmenow_log_u_ix (not unique) to be added to block_helpmenow_log
        $table = new xmldb_table('block_helpmenow_log');
        $index = new xmldb_index('block_helpmenow_log_u_ix');
        $index->set_attributes(XMLDB_INDEX_NOTUNIQUE, array('userid'));

        /// Launch add index block_helpmenow_log_u_ix
        $result = $result && $dbman->add_index($table, $index);

        /// Define index block_helpmenow_log_ua_ix (not unique) to be added to block_helpmenow_log
        $table = new xmldb_table('block_helpmenow_log');
        $index = new xmldb_index('block_helpmenow_log_ua_ix');
        $index->set_attributes(XMLDB_INDEX_NOTUNIQUE, array('userid', 'action'));

        /// Launch add index block_helpmenow_log_ua_ix
        $result = $result && $dbman->add_index($table, $index);
    }

    if ($result && $oldversion < 2013050700) {

    /// Define field last_read to be added to block_helpmenow_session2user
        $table = new xmldb_table('block_helpmenow_session2user');
        $field = new xmldb_field('last_read');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, null, null, '0', 'cache');

        /// Launch add field last_read
        $result = $result && $dbman->add_field($table, $field);
    }

    if ($result && $oldversion < 2014050401) {

        // Reminder: do not use core/lib function in upgrade script!
        $contextid = $DB->get_field('context', 'id', array('contextlevel' => CONTEXT_SYSTEM));

        // Hardcode the capability as they must match the value at this upgrade time.
        $HELPMENOW_CAP_QUEUE_ANSWER = 'block/helpmenow:global_queue_answer';
        $HELPMENOW_CAP_QUEUE_ASK = 'block/helpmenow:queue_ask';
        $HELPMENOW_CAP_MANAGE = 'block/helpmenow:manage_queues';

        // Add Help Me Now block manager system role.
        $role = new stdClass();
        $role->name        = 'Help Me Now Manager';
        $role->shortname   = 'helpmenowmanager';
        $role->description = 'can assign a queue helper - can do anything on helpmenow.';
        // Find free sortorder number.
        $role->sortorder = $DB->get_field('role', 'MAX(sortorder) + 1', array());
        if (empty($role->sortorder)) {
            $role->sortorder = 1;
        }
        $roleid = $DB->insert_record('role', $role);
        // Set the role as system role.
        $rcl = new stdClass();
        $rcl->roleid = $roleid;
        $rcl->contextlevel = CONTEXT_SYSTEM;
        $DB->insert_record('role_context_levels', $rcl, false, true);
        // Assign correct permission to Help Me Now block manager role.
        $cap = new stdClass();
        $cap->contextid    = $contextid;
        $cap->roleid       = $roleid;
        $cap->capability   = $HELPMENOW_CAP_MANAGE;
        $cap->permission   = 1;
        $cap->timemodified = time();
        $cap->modifierid   = empty($USER->id) ? 0 : $USER->id;
        $DB->insert_record('role_capabilities', $cap);
        $cap->capability   = $HELPMENOW_CAP_QUEUE_ANSWER;
        $DB->insert_record('role_capabilities', $cap);
        $cap->capability   = $HELPMENOW_CAP_QUEUE_ASK;
        $DB->insert_record('role_capabilities', $cap);

        // Add Help Me Now block instructor system role.
        $role = new stdClass();
        $role->name        = 'Help Me Now instructor';
        $role->shortname   = 'helpmenowinstructor';
        $role->description = 'can login in an office and answer questions.';
        $role->sortorder = $DB->get_field('role', 'MAX(sortorder) + 1', array());
        $roleid = $DB->insert_record('role', $role);
        $rcl = new stdClass();
        $rcl->roleid = $roleid;
        $rcl->contextlevel = CONTEXT_SYSTEM;
        $DB->insert_record('role_context_levels', $rcl, false, true);
        $cap->roleid       = $roleid;
        $cap->capability   = $HELPMENOW_CAP_QUEUE_ASK;
        $DB->insert_record('role_capabilities', $cap);
        $cap->capability   = $HELPMENOW_CAP_QUEUE_ANSWER;
        $DB->insert_record('role_capabilities', $cap);

        // Add Help Me Now block student system role.
        $role = new stdClass();
        $role->name        = 'Help Me Now student';
        $role->shortname   = 'helpmenowstudent';
        $role->description = 'can ask questions to instructors and helpers.';
        $role->sortorder = $DB->get_field('role', 'MAX(sortorder) + 1', array());
        $roleid = $DB->insert_record('role', $role);
        $rcl = new stdClass();
        $rcl->roleid = $roleid;
        $rcl->contextlevel = CONTEXT_SYSTEM;
        $DB->insert_record('role_context_levels', $rcl, false, true);
        $cap->roleid       = $roleid;
        $cap->capability   = $HELPMENOW_CAP_QUEUE_ASK;
        $DB->insert_record('role_capabilities', $cap);

        // Helpmenow savepoint reached.
        upgrade_block_savepoint(true, 2014050401, 'helpmenow');
    }

    return $result;
}

