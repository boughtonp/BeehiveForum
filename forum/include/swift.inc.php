<?php

/*======================================================================
Copyright Project Beehive Forum 2002

This file is part of Beehive Forum.

Beehive Forum is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

Beehive Forum is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Beehive; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307
USA
======================================================================*/

/* $Id: swift.inc.php,v 1.1 2009-10-10 13:18:30 decoyduck Exp $ */

// We shouldn't be accessing this file directly.

if (basename($_SERVER['SCRIPT_NAME']) == basename(__FILE__)) {
    header("Request-URI: ../index.php");
    header("Content-Location: ../index.php");
    header("Location: ../index.php");
    exit;
}

// Include Swift Mailer

include_once(BH_INCLUDE_PATH. "/swift/swift_required.php");

// Swift Mailer Transport Factory

abstract class Swift_TransportFactory
{
    public static function get()
    {
        if (($smtp_server = forum_get_global_setting('smtp_server'))) {
            
            $smtp_port = forum_get_global_setting('smtp_port', false, '25');
            return Swift_SmtpTransportSingleton::getInstance($smtp_server, $smtp_port);
        
        } else {

            return Swift_MailTransportSingleton::getInstance();
        }
    }
}

// Swift Mailer SMTP Transport Singleton wrapper

class Swift_SmtpTransportSingleton
{
    private static $instance;

    private function __construct() { }
    
    public static function getInstance($smtp_server, $smtp_port)
    {
        if (is_null(self::$instance)) {
            self::$instance = Swift_SmtpTransport::newInstance($smtp_server, $smtp_port);
        }

        return self::$instance;
    }
}

// Swift Mailer Mail Transport Singleton wrapper

class Swift_MailTransportSingleton
{
    private static $instance;

    private function __construct() { }
    
    public static function getInstance()
    {
        if (!self::check_mail_vars()) return false;
        
        if (is_null(self::$instance)) {
            self::$instance = Swift_MailTransport::newInstance();
        }

        return self::$instance;
    }

    private static function check_mail_vars()
    {
        if (server_os_mswin()) {
            if (!(bool)ini_get('sendmail_from') || !(bool)ini_get('SMTP')) return false;
        }else {
            if (!(bool)@ini_get('sendmail_path')) return false;
        }

        return true;
    }
}

class Swift_MessageBeehive extends Swift_Message
{
    public function __construct($subject = null, $body = null, $contentType = null, $charset = null)
    {
        // Call the parent constructor.
        
        parent::__construct($subject, $body, $contentType, $charset);

        // Set the Beehive specific headers
        
        $this->set_headers();
    }

    public static function newInstance($subject = null, $body = null, $contentType = null, $charset = null)
    {
        return new self($subject, $body, $contentType, $charset);
    }

    private function set_headers()
    {
        // Get the forum name.
        
        $forum_name  = forum_get_setting('forum_name', false, 'A Beehive Forum');

        // Get the forum email address
        
        $forum_email = forum_get_setting('forum_noreply_email', false, 'noreply@abeehiveforum.net');
        
        // Get the Swift Headers set

        $headers = $this->getHeaders();

        // Add PHP version number to headers

        $headers->addTextHeader('X-Mailer', 'PHP/'. phpversion());

        // Add the Beehive version number to headers

        $headers->addTextHeader('X-Beehive-Forum', 'Beehive Forum '. BEEHIVE_VERSION);

        // Add header to identify Swift version

        $headers->addTextHeader('X-Swift-Mailer', 'Swift Mailer '. Swift::VERSION);

        // Set the Message From Header

        $this->setFrom($forum_email, $forum_name);

        // Set the Message Reply-To Header

        $this->setReplyTo($forum_email, $forum_name);

        // Set the Message Return-path Header

        $this->setReturnPath($forum_email);
    }
}

?>