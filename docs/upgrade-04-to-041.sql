# Beehive Forum Database Creation
# version 0.4 to 0.4.1 Upgrade
# http://beehiveforum.sourceforge.net/
#
# Generation Time: Mar 16, 2004 at 00:17
#
# $Id: upgrade-04-to-041.sql,v 1.15 2004-03-17 20:41:50 decoyduck Exp $
#
# --------------------------------------------------------#

CREATE TABLE BANNED_IP_NEW (
  IP CHAR(15) NOT NULL DEFAULT '',
  PRIMARY KEY  (IP)
) TYPE=MYISAM;

INSERT INTO BANNED_IP_NEW (IP) SELECT DISTINCT IP FROM BANNED_IP;

DROP TABLE BANNED_IP;

ALTER TABLE BANNED_IP_NEW RENAME BANNED_IP;

CREATE TABLE LINKS_VOTE_NEW (
  LID SMALLINT(5) UNSIGNED NOT NULL DEFAULT '0',
  UID MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',
  RATING SMALLINT(5) UNSIGNED NOT NULL DEFAULT '0',
  TSTAMP DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (LID, UID)
) TYPE=MYISAM;

INSERT INTO LINKS_VOTE_NEW (LID, UID, RATING, TSTAMP) 
SELECT DISTINCT LID, UID, RATING, TSTAMP FROM LINKS_VOTE GROUP BY LID, UID;

DROP TABLE LINKS_VOTE;

ALTER TABLE LINKS_VOTE_NEW RENAME LINKS_VOTE;

CREATE TABLE POST_ATTACHMENT_IDS_NEW (
  TID MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',
  PID MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',
  AID CHAR(32) NOT NULL DEFAULT '',
  PRIMARY KEY  (TID,PID),
  KEY AID (AID)
) TYPE=MYISAM;

INSERT INTO POST_ATTACHMENT_IDS_NEW (TID, PID, AID)
SELECT DISTINCT TID, PID, AID FROM POST_ATTACHMENT_IDS GROUP BY TID, PID;

DROP TABLE POST_ATTACHMENT_IDS;

ALTER TABLE POST_ATTACHMENT_IDS_NEW RENAME POST_ATTACHMENT_IDS;

CREATE TABLE STATS_NEW (
  ID MEDIUMINT(8) UNSIGNED NOT NULL AUTO_INCREMENT,
  MOST_USERS_DATE DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
  MOST_USERS_COUNT MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',
  MOST_POSTS_DATE DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
  MOST_POSTS_COUNT MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY  (ID)
) TYPE=MYISAM;

INSERT INTO STATS_NEW (MOST_USERS_DATE, MOST_USERS_COUNT, MOST_POSTS_DATE, MOST_POSTS_COUNT)
SELECT DISTINCT MOST_USERS_DATE, MOST_USERS_COUNT, MOST_POSTS_DATE, MOST_POSTS_COUNT
FROM STATS;

DROP TABLE STATS;

ALTER TABLE STATS_NEW RENAME STATS;

CREATE TABLE USER_FOLDER_NEW (
  UID MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',
  FID MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',
  INTEREST TINYINT(4) DEFAULT '0',
  ALLOWED TINYINT(4) DEFAULT '0',
  PRIMARY KEY  (UID,FID)
) TYPE=MYISAM;

INSERT INTO USER_FOLDER_NEW (UID, FID, INTEREST, ALLOWED)
SELECT DISTINCT UID, FID, INTEREST, ALLOWED FROM USER_FOLDER
GROUP BY UID, FID;

DROP TABLE USER_FOLDER;

ALTER TABLE USER_FOLDER_NEW RENAME USER_FOLDER;

CREATE TABLE USER_PEER_NEW (
  UID MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',
  PEER_UID MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',
  RELATIONSHIP TINYINT(4) DEFAULT NULL,
  PRIMARY KEY  (UID,PEER_UID)
) TYPE=MYISAM;

INSERT INTO USER_PEER_NEW (UID, PEER_UID, RELATIONSHIP)
SELECT DISTINCT UID, PEER_UID, RELATIONSHIP FROM USER_PEER
GROUP BY UID, PEER_UID;

DROP TABLE USER_PEER;

ALTER TABLE USER_PEER_NEW RENAME USER_PEER;

CREATE TABLE USER_PREFS_NEW (
  UID MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',
  FIRSTNAME VARCHAR(32) DEFAULT NULL,
  LASTNAME VARCHAR(32) DEFAULT NULL,
  DOB DATE DEFAULT '0000-00-00',
  HOMEPAGE_URL VARCHAR(255) DEFAULT NULL,
  PIC_URL VARCHAR(255) DEFAULT NULL,
  EMAIL_NOTIFY CHAR(1) DEFAULT NULL,
  TIMEZONE DECIMAL(2,1) DEFAULT NULL,
  DL_SAVING CHAR(1) DEFAULT NULL,
  MARK_AS_OF_INT CHAR(1) DEFAULT NULL,
  POSTS_PER_PAGE TINYINT(3) UNSIGNED DEFAULT NULL,
  FONT_SIZE TINYINT(3) UNSIGNED DEFAULT NULL,
  STYLE VARCHAR(255) DEFAULT NULL,
  VIEW_SIGS CHAR(1) DEFAULT NULL,
  START_PAGE TINYINT(3) UNSIGNED DEFAULT NULL,
  LANGUAGE VARCHAR(32) DEFAULT NULL,
  PM_NOTIFY CHAR(1) DEFAULT NULL,
  PM_NOTIFY_EMAIL CHAR(1) DEFAULT NULL,
  DOB_DISPLAY TINYINT(3) UNSIGNED DEFAULT NULL,
  ANON_LOGON TINYINT(3) UNSIGNED DEFAULT NULL,
  SHOW_STATS TINYINT(3) UNSIGNED DEFAULT NULL,
  PRIMARY KEY  (UID,UID)
) TYPE=MYISAM;

INSERT INTO USER_PREFS_NEW (UID, FIRSTNAME, LASTNAME, DOB, HOMEPAGE_URL, PIC_URL,
EMAIL_NOTIFY, TIMEZONE, DL_SAVING, MARK_AS_OF_INT, POSTS_PER_PAGE, FONT_SIZE, STYLE,
VIEW_SIGS, START_PAGE, LANGUAGE, PM_NOTIFY, PM_NOTIFY_EMAIL, DOB_DISPLAY, ANON_LOGON,
SHOW_STATS) SELECT DISTINCT UID, FIRSTNAME, LASTNAME, DOB, HOMEPAGE_URL, PIC_URL,
EMAIL_NOTIFY, TIMEZONE, DL_SAVING, MARK_AS_OF_INT, POSTS_PER_PAGE, FONT_SIZE,
STYLE, VIEW_SIGS, START_PAGE, LANGUAGE, PM_NOTIFY, PM_NOTIFY_EMAIL, DOB_DISPLAY,
ANON_LOGON, SHOW_STATS FROM USER_PREFS GROUP BY UID;

DROP TABLE USER_PREFS;

ALTER TABLE USER_PREFS_NEW RENAME USER_PREFS;

CREATE TABLE USER_PROFILE_NEW (
  UID MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',
  PIID MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',
  ENTRY VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY  (UID,PIID)
) TYPE=MYISAM;

INSERT INTO USER_PROFILE_NEW (UID, PIID, ENTRY)
SELECT DISTINCT UID, PIID, ENTRY FROM USER_PROFILE
GROUP BY UID, PIID;

DROP TABLE USER_PROFILE;

ALTER TABLE USER_PROFILE_NEW RENAME USER_PROFILE;

CREATE TABLE USER_SIG_NEW (
  UID MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',
  CONTENT TEXT,
  HTML CHAR(1) DEFAULT NULL,
  PRIMARY KEY  (UID)
) TYPE=MYISAM;

INSERT INTO USER_SIG_NEW (UID, CONTENT, HTML)
SELECT DISTINCT UID, CONTENT, HTML FROM USER_SIG
GROUP BY UID;

DROP TABLE USER_SIG;

ALTER TABLE USER_SIG_NEW RENAME USER_SIG;

CREATE TABLE USER_THREAD_NEW (
  UID MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT 0,
  TID MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT 0,
  LAST_READ MEDIUMINT(8) UNSIGNED DEFAULT NULL,
  LAST_READ_AT DATETIME DEFAULT NULL,
  INTEREST TINYINT(4) DEFAULT NULL,
  PRIMARY KEY (UID, TID)
) TYPE=MYISAM;

INSERT INTO USER_THREAD_NEW (UID, TID, LAST_READ, LAST_READ_AT, INTEREST) 
SELECT DISTINCT UID, TID, LAST_READ, LAST_READ_AT, INTEREST FROM USER_THREAD GROUP BY UID, TID;

DROP TABLE USER_THREAD;

ALTER TABLE USER_THREAD_NEW RENAME USER_THREAD;

ALTER TABLE THREAD ADD ADMIN_LOCK DATETIME DEFAULT NULL;

ALTER TABLE PM ADD NOTIFIED TINYINT(1) UNSIGNED DEFAULT '0' NOT NULL;
UPDATE PM SET NOTIFIED = 1 WHERE TYPE > 1;

ALTER TABLE POLL_VOTES DROP VOTES;
ALTER TABLE USER_PREFS ADD IMAGES_TO_LINKS CHAR(1);

ALTER TABLE USER_PREFS ADD USE_WORD_FILTER CHAR(1);
ALTER TABLE USER_PREFS ADD USE_ADMIN_FILTER CHAR(1);

ALTER TABLE FILTER_LIST ADD UID MEDIUMINT(8) UNSIGNED DEFAULT '0' NOT NULL;
ALTER TABLE FILTER_LIST CHANGE FILTER MATCH_TEXT VARCHAR(255) NOT NULL;
ALTER TABLE FILTER_LIST ADD REPLACE_TEXT VARCHAR(255) NOT NULL;
ALTER TABLE FILTER_LIST ADD PREG_EXPR TINYINT(1) UNSIGNED DEFAULT '0' NOT NULL;

ALTER TABLE SESSIONS ADD FID MEDIUMINT(8) UNSIGNED DEFAULT '0' NOT NULL;
ALTER TABLE SESSIONS ADD INDEX (FID);
ALTER TABLE SESSIONS ADD INDEX (UID);

CREATE TABLE FORUMS (
  FID MEDIUMINT(8) UNSIGNED NOT NULL AUTO_INCREMENT,
  WEBTAG VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (FID)
) TYPE=MyISAM;

INSERT INTO FORUMS (WEBTAG) VALUES('');

CREATE TABLE FORUM_SETTINGS (
  SID MEDIUMINT(8) UNSIGNED NOT NULL AUTO_INCREMENT,
  FID MEDIUMINT(8) UNSIGNED NOT NULL,  
  SNAME VARCHAR(255) NOT NULL,
  SVALUE VARCHAR(255) NOT NULL,
  INDEX (SID, FID)
) TYPE=MyISAM;

INSERT INTO FORUM_SETTINGS (FID, SNAME, SVALUE) VALUES (1, 'forum_name', 'A Beehive Forum');
INSERT INTO FORUM_SETTINGS (FID, SNAME, SVALUE) VALUES (1, 'forum_email', 'admin@abeehiveforum.net');
INSERT INTO FORUM_SETTINGS (FID, SNAME, SVALUE) VALUES (1, 'default_style', 'default');
INSERT INTO FORUM_SETTINGS (FID, SNAME, SVALUE) VALUES (1, 'default_language', 'en');
INSERT INTO FORUM_SETTINGS (FID, SNAME, SVALUE) VALUES (1, 'show_friendly_errors', 'Y');
INSERT INTO FORUM_SETTINGS (FID, SNAME, SVALUE) VALUES (1, 'cookie_domain', '');
INSERT INTO FORUM_SETTINGS (FID, SNAME, SVALUE) VALUES (1, 'show_stats', 'Y');
INSERT INTO FORUM_SETTINGS (FID, SNAME, SVALUE) VALUES (1, 'show_links', 'Y');
INSERT INTO FORUM_SETTINGS (FID, SNAME, SVALUE) VALUES (1, 'auto_logon', 'Y');
INSERT INTO FORUM_SETTINGS (FID, SNAME, SVALUE) VALUES (1, 'show_pms', 'Y');
INSERT INTO FORUM_SETTINGS (FID, SNAME, SVALUE) VALUES (1, 'pm_allow_attachments', 'Y');
INSERT INTO FORUM_SETTINGS (FID, SNAME, SVALUE) VALUES (1, 'maximum_post_length', '6226');
INSERT INTO FORUM_SETTINGS (FID, SNAME, SVALUE) VALUES (1, 'allow_post_editing', 'Y');
INSERT INTO FORUM_SETTINGS (FID, SNAME, SVALUE) VALUES (1, 'post_edit_time', '0');
INSERT INTO FORUM_SETTINGS (FID, SNAME, SVALUE) VALUES (1, 'allow_polls', 'Y');
INSERT INTO FORUM_SETTINGS (FID, SNAME, SVALUE) VALUES (1, 'search_min_word_length', '3');
INSERT INTO FORUM_SETTINGS (FID, SNAME, SVALUE) VALUES (1, 'attachments_enabled', 'Y');
INSERT INTO FORUM_SETTINGS (FID, SNAME, SVALUE) VALUES (1, 'attachments_dir', 'attachments');
INSERT INTO FORUM_SETTINGS (FID, SNAME, SVALUE) VALUES (1, 'attachments_show_deleted', 'N');
INSERT INTO FORUM_SETTINGS (FID, SNAME, SVALUE) VALUES (1, 'attachments_allow_embed', 'N');
INSERT INTO FORUM_SETTINGS (FID, SNAME, SVALUE) VALUES (1, 'attachments_use_old_method', 'N');
INSERT INTO FORUM_SETTINGS (FID, SNAME, SVALUE) VALUES (1, 'guest_account_active', 'Y');
INSERT INTO FORUM_SETTINGS (FID, SNAME, SVALUE) VALUES (1, 'session_cutoff', '86400');
INSERT INTO FORUM_SETTINGS (FID, SNAME, SVALUE) VALUES (1, 'active_session_cutoff', '900');
INSERT INTO FORUM_SETTINGS (FID, SNAME, SVALUE) VALUES (1, 'gzip_compress_output', 'Y');
INSERT INTO FORUM_SETTINGS (FID, SNAME, SVALUE) VALUES (1, 'gzip_compress_level', '1');

CREATE TABLE START_MAIN (
  FID MEDIUMINT(8) UNSIGNED NOT NULL,
  HTML TEXT NOT NULL,
  PRIMARY KEY (FID)
) TYPE=MyISAM;

INSERT INTO START_MAIN (FID, HTML) VALUES (1, '<!doctype HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">\n<html>\n<head>\n<title>Project Beehive</title>\n<style type="text/css">\n<!--\n\n.bodytext    { font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 11px;\n               font-style: normal; line-height: 13px; font-weight: normal; color: #666666;\n               background-color: #EAEFF4 }\n\n.title       { font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 18px;\n               font-style: normal; font-weight: bold; color: #ffffff; background-color: #A6BED7 }\n\na            { font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 11px;\n               line-height: 13px; font-weight: normal; color: #333399;\n               text-decoration: underline }\n\n-->\n</style>\n</head>\n\n<body class="bodytext">\n<table width="100%" border="0" cellspacing="0" cellpadding="8">\n  <tr>\n    <td class="title">Welcome to your new Beehive Forum!</td>\n  </tr>\n  <tr>\n    <td class="bodytext"><a href="http://sourceforge.net/projects/beehiveforum/" target="_blank">Home</a> | <a href="http://beehiveforum.net/faq">FAQ</a> | <a href="http://sourceforge.net/docman/?group_id=50772" target="_blank">Docs</a> | <a href="http://sourceforge.net/project/showfiles.php?group_id=50772" target="_blank"> Download</a> | <a href="../forums.php">Live Forums</a></td>\n  </tr>\n  <tr>\n    <td height="1" class="title"></td>\n  </tr>\n  <tr>\n    <td valign="top" class="bodytext">\n      <p>You can modify this start page from the admin interface.</p>\n    </td>\n  </tr>\n  <tr>\n    <td height="1" class="title"> </td>\n  </tr>\n</table>\n</body>\n</html>');

CREATE TABLE VISITOR_LOG (
  UID MEDIUMINT(8) UNSIGNED NOT NULL,
  FID MEDIUMINT(8) UNSIGNED NOT NULL,
  LAST_LOGON DATETIME NOT NULL,
  PRIMARY KEY (UID, FID)
) TYPE=MyISAM;

CREATE TABLE USER_STATUS (
  UID MEDIUMINT(8) UNSIGNED NOT NULL,
  FID MEDIUMINT(8) UNSIGNED NOT NULL,
  STATUS INT(16) NOT NULL,
  PRIMARY KEY (UID, FID)
) TYPE=MyISAM;

INSERT INTO USER_STATUS (UID, FID, STATUS) SELECT UID, 1, STATUS FROM USER;

ALTER TABLE USER DROP LAST_LOGON;
ALTER TABLE USER DROP LOGON_FROM;
ALTER TABLE USER DROP STATUS;

ALTER TABLE USER_PREFS ADD EMOTICONS VARCHAR(255);
