<?php
/**
 * Schoolbox Auth
 *
 * Allows Schoolbox users to login and have their identities automatically populated
 *
 */
class schoolbox_auth extends rcube_plugin {

    public $task = 'login';

    function init () {
        require_once '../../../include/stdfuncs.php';

        $this->add_hook('startup', array( $this, 'startup' ));
        $this->add_hook('authenticate', array( $this, 'authenticate' ));
        $this->add_hook('user_create', array( $this, 'user_create' ));
    }

    function startup ($args) {
        // change action to login
        if ( empty( $args['action'] ) && empty( $_SESSION['user_id'] ) && !empty( $_SESSION['username'] ) && ( !empty( $_SESSION['email_pass'] ) || !empty( $_SESSION['password'] ) ) ) {
            $args['action'] = 'login';
        }

        return $args;
    }

    function authenticate ($args) {
        if ( !empty( $_SESSION['username'] ) && ( !empty($_SESSION['email_pass']) || !empty($_SESSION['password']) ) ) {
            $args['user'] = $_SESSION['username'];

            /* Fallback to $_SESSION['password'] if $_SESSION['email_pass'] is empty */
            if ( !empty($_SESSION['email_pass']) ) {
                $args['pass'] = $_SESSION['email_pass'];
            }
            else {
                $args['pass'] = $_SESSION['password'];
            }

            $args['valid'] = true;
        }

        $args['cookiecheck'] = false;

        return $args;
    }

    function user_create ($args) {
        $args['user_name'] = $userHandler->formatName($_SESSION);
        
        return $args;
    }

}
?>