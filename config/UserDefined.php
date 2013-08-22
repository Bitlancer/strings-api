<?php

namespace Application;

/**
 * This file can be used to define application wide constants
 * or functions (should be avoided).
 */

/*
 * The remote execution process will drop temporary
 * private key files into this directory.
 */
define('REMOTE_EXECUTION_PRIVATE_KEY_DIR','/tmp');

/**
 * User account used to connect to the jump servers
 * to execute a remote script.
 */
define('REMOTE_EXECUTION_USER','remoteexec');
