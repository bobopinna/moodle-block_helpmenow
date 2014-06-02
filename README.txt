Moodle Block: Helpmenow 
Copyright: VLACS 2013 www.vlacs.org
License: http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

Plugin branch names and matching Moodle version
MOODLE_19_STABLE => Moodle 1.9
master => Moodle 2.2 to 2.7
alternate_server (it is the old master branch containing alternate server code) => Moodle 1.9

Intro:
This moodle block creates a chat interface for students and teachers to
connect.  It also provides a help desk chat interface for students to ask
questions of the teachers/admins who are assigned as helpers and currently
logged in. It also has plugins for multiple sharing tools that can be enabled.

Requires:
Client side javascript

Features:

- Help Desk Chat:
  - Admins can create any number of help desks, ie: Technical Help, Academic
    Help, etc.
  - Users are added as helpers to the help desk queues by adding permissions
    for them, then assigning them to helpers using the Manage Queues Link.
    (Admins only)
  - These queues are designed so that multiple teachers/admins can log in to help
    students with questions during specific hours.
  - Allow all authenticated users the permission block/helpmenow:queue_ask to be
    able to ask questions to the queues.

- Teacher/Student Chat:
  - Lists are managed with personalized contact_list plugin system. 
  - The native contact list provided queries the course context to determine if
    the user is a student or a teacher (by role).
     - Users who are teachers will be able to chat with all users who have
       a student role in the courses that they are a teacher or editingteacher
       for. 
     - Users who are students will be able to chat with all users who are
       assigned as teachers in the courses they are in.
  - The contact list is a plugin system that can be customized to use extra data
    availible in your system to determine roles/contact lists.
  - The native contact lists provided for the 1.9 version have only been used
    for basic testing, we (vlacs) use a customized version of these lists. When
    the 2.x version of this plugin is availible, we hope to provide a much
    better version of the contact lists utilizing the updated Student/Teacher
    roles. 

- Teachers and help desk helpers are not automatically logged in to the system
  when they use moodle, they should log in during the times they are available
  to answer students questions. All students are automatically logged into the
  helpmenow block
- Teachers may enter a Message of the Day (MOTD) which is their current status,
  this is displayed to students.
- Missed Chats are emailed to the user.
- Chat history is availible to all users.

- Updates are not instantaneous, things may take up to 10 seconds to refresh
  (like logging in and out of the block, lists of students online)
- This is designed to not allow students to chat with other students. However it
  is dependant on how the contact_lists are setup.
- Admins may see "Who's Here" which shows a list of all teachers and their
  status and if they are online. It also shows which helpers are currently
  staffing the help desk.
- Use block settings to change the name of the block to personalize


- Plugins to enable sharing tools
  - Adobe Connect
  - WizIQ (obsolete / not maintained)
  - GoToMeeting (obsolete / not maintained)

  - Links are provided in chat to invite students to join the teacher in the
    shared area.



