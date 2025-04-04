<?php
/**
 * Functions for the replication GUI
 */

declare(strict_types=1);

namespace PhpMyAdmin;

use PhpMyAdmin\Query\Utilities;

use function htmlspecialchars;
use function in_array;
use function is_array;
use function mb_strrpos;
use function mb_strtolower;
use function mb_substr;
use function sprintf;
use function str_replace;
use function strlen;
use function strtok;
use function time;

/**
 * Functions for the replication GUI
 */
class ReplicationGui
{
    /** @var Replication */
    private $replication;

    /** @var Template */
    private $template;

    /**
     * @param Replication $replication Replication instance
     * @param Template    $template    Template instance
     */
    public function __construct(Replication $replication, Template $template)
    {
        $this->replication = $replication;
        $this->template = $template;
    }

    /**
     * returns HTML for error message
     *
     * @return string HTML code
     */
    public function getHtmlForErrorMessage()
    {
        $html = '';
        if (isset($_SESSION['replication']['sr_action_status'], $_SESSION['replication']['sr_action_info'])) {
            if ($_SESSION['replication']['sr_action_status'] === 'error') {
                $errorMessage = $_SESSION['replication']['sr_action_info'];
                $html .= Message::error($errorMessage)->getDisplay();
                $_SESSION['replication']['sr_action_status'] = 'unknown';
            } elseif ($_SESSION['replication']['sr_action_status'] === 'success') {
                $successMessage = $_SESSION['replication']['sr_action_info'];
                $html .= Message::success($successMessage)->getDisplay();
                $_SESSION['replication']['sr_action_status'] = 'unknown';
            }
        }

        return $html;
    }

    /**
     * returns HTML for master replication
     *
     * @return string HTML code
     */
    public function getHtmlForMasterReplication()
    {
        global $dbi;

        if (! isset($_POST['repl_clear_scr'])) {
            $masterStatusTable = $this->getHtmlForReplicationStatusTable('master', true, false);
            $slaves = $dbi->fetchResult('SHOW SLAVE HOSTS', null, null);

            $urlParams = $GLOBALS['url_params'];
            $urlParams['mr_adduser'] = true;
            $urlParams['repl_clear_scr'] = true;
        }

        if (isset($_POST['mr_adduser'])) {
            $masterAddSlaveUser = $this->getHtmlForReplicationMasterAddSlaveUser();
        }

        return $this->template->render('server/replication/master_replication', [
            'clear_screen' => isset($_POST['repl_clear_scr']),
            'master_status_table' => $masterStatusTable ?? '',
            'slaves' => $slaves ?? [],
            'url_params' => $urlParams ?? [],
            'master_add_user' => isset($_POST['mr_adduser']),
            'master_add_slave_user' => $masterAddSlaveUser ?? '',
        ]);
    }

    /**
     * returns HTML for master replication configuration
     *
     * @return string HTML code
     */
    public function getHtmlForMasterConfiguration()
    {
        $databaseMultibox = $this->getHtmlForReplicationDbMultibox();

        return $this->template->render(
            'server/replication/master_configuration',
            ['database_multibox' => $databaseMultibox]
        );
    }

    /**
     * returns HTML for slave replication configuration
     *
     * @param bool  $serverSlaveStatus      Whether it is Master or Slave
     * @param array $serverSlaveReplication Slave replication
     *
     * @return string HTML code
     */
    public function getHtmlForSlaveConfiguration(
        $serverSlaveStatus,
        array $serverSlaveReplication
    ) {
        global $dbi;

        $serverSlaveMultiReplication = $dbi->fetchResult(
            'SHOW ALL SLAVES STATUS'
        );
        if ($serverSlaveStatus) {
            $urlParams = $GLOBALS['url_params'];
            $urlParams['sr_take_action'] = true;
            $urlParams['sr_slave_server_control'] = true;

            if ($serverSlaveReplication[0]['Slave_IO_Running'] === 'No') {
                $urlParams['sr_slave_action'] = 'start';
            } else {
                $urlParams['sr_slave_action'] = 'stop';
            }

            $urlParams['sr_slave_control_parm'] = 'IO_THREAD';
            $slaveControlIoLink = Url::getCommon($urlParams, '');

            if ($serverSlaveReplication[0]['Slave_SQL_Running'] === 'No') {
                $urlParams['sr_slave_action'] = 'start';
            } else {
                $urlParams['sr_slave_action'] = 'stop';
            }

            $urlParams['sr_slave_control_parm'] = 'SQL_THREAD';
            $slaveControlSqlLink = Url::getCommon($urlParams, '');

            if (
                $serverSlaveReplication[0]['Slave_IO_Running'] === 'No'
                || $serverSlaveReplication[0]['Slave_SQL_Running'] === 'No'
            ) {
                $urlParams['sr_slave_action'] = 'start';
            } else {
                $urlParams['sr_slave_action'] = 'stop';
            }

            $urlParams['sr_slave_control_parm'] = null;
            $slaveControlFullLink = Url::getCommon($urlParams, '');

            $urlParams['sr_slave_action'] = 'reset';
            $slaveControlResetLink = Url::getCommon($urlParams, '');

            $urlParams = $GLOBALS['url_params'];
            $urlParams['sr_take_action'] = true;
            $urlParams['sr_slave_skip_error'] = true;
            $slaveSkipErrorLink = Url::getCommon($urlParams, '');

            $urlParams = $GLOBALS['url_params'];
            $urlParams['sl_configure'] = true;
            $urlParams['repl_clear_scr'] = true;

            $reconfigureMasterLink =  Url::getCommon($urlParams, '');

            $slaveStatusTable = $this->getHtmlForReplicationStatusTable('slave', true, false);

            $slaveIoRunning = $serverSlaveReplication[0]['Slave_IO_Running'] !== 'No';
            $slaveSqlRunning = $serverSlaveReplication[0]['Slave_SQL_Running'] !== 'No';
        }

        return $this->template->render('server/replication/slave_configuration', [
            'server_slave_multi_replication' => $serverSlaveMultiReplication,
            'url_params' => $GLOBALS['url_params'],
            'master_connection' => $_POST['master_connection'] ?? '',
            'server_slave_status' => $serverSlaveStatus,
            'slave_status_table' => $slaveStatusTable ?? '',
            'slave_sql_running' => $slaveSqlRunning ?? false,
            'slave_io_running' => $slaveIoRunning ?? false,
            'slave_control_full_link' => $slaveControlFullLink ?? '',
            'slave_control_reset_link' => $slaveControlResetLink ?? '',
            'slave_control_sql_link' => $slaveControlSqlLink ?? '',
            'slave_control_io_link' => $slaveControlIoLink ?? '',
            'slave_skip_error_link' => $slaveSkipErrorLink ?? '',
            'reconfigure_master_link' => $reconfigureMasterLink ?? '',
            'has_slave_configure' => isset($_POST['sl_configure']),
        ]);
    }

    /**
     * returns HTML code for selecting databases
     *
     * @return string HTML code
     */
    public function getHtmlForReplicationDbMultibox()
    {
        $databases = [];
        foreach ($GLOBALS['dblist']->databases as $database) {
            if (Utilities::isSystemSchema($database)) {
                continue;
            }

            $databases[] = $database;
        }

        return $this->template->render('server/replication/database_multibox', ['databases' => $databases]);
    }

    /**
     * returns HTML for changing master
     *
     * @param string $submitName submit button name
     *
     * @return string HTML code
     */
    public function getHtmlForReplicationChangeMaster($submitName)
    {
        [
            $usernameLength,
            $hostnameLength,
        ] = $this->getUsernameHostnameLength();

        return $this->template->render('server/replication/change_master', [
            'server_id' => time(),
            'username_length' => $usernameLength,
            'hostname_length' => $hostnameLength,
            'submit_name' => $submitName,
        ]);
    }

    /**
     * This function returns html code for table with replication status.
     *
     * @param string $type     either master or slave
     * @param bool   $isHidden if true, then default style is set to hidden,
     *                         default value false
     * @param bool   $hasTitle if true, then title is displayed, default true
     *
     * @return string HTML code
     */
    public function getHtmlForReplicationStatusTable(
        $type,
        $isHidden = false,
        $hasTitle = true
    ): string {
        global $dbi;

        $replicationInfo = new ReplicationInfo($dbi);
        $replicationInfo->load($_POST['master_connection'] ?? null);

        $replicationVariables = $replicationInfo->primaryVariables;
        $variablesAlerts = null;
        $variablesOks = null;
        $serverReplication = $replicationInfo->getPrimaryStatus();
        if ($type === 'slave') {
            $replicationVariables = $replicationInfo->replicaVariables;
            $variablesAlerts = [
                'Slave_IO_Running' => 'No',
                'Slave_SQL_Running' => 'No',
            ];
            $variablesOks = [
                'Slave_IO_Running' => 'Yes',
                'Slave_SQL_Running' => 'Yes',
            ];
            $serverReplication = $replicationInfo->getReplicaStatus();
        }

        $variables = [];
        foreach ($replicationVariables as $variable) {
            $serverReplicationVariable = is_array($serverReplication) && isset($serverReplication[0])
                ? $serverReplication[0][$variable]
                : '';

            $variables[$variable] = [
                'name' => $variable,
                'status' => '',
                'value' => $serverReplicationVariable,
            ];

            if (
                isset($variablesAlerts[$variable])
                && $variablesAlerts[$variable] === $serverReplicationVariable
            ) {
                $variables[$variable]['status'] = 'attention';
            } elseif (
                isset($variablesOks[$variable])
                && $variablesOks[$variable] === $serverReplicationVariable
            ) {
                $variables[$variable]['status'] = 'allfine';
            }

            $variablesWrap = [
                'Replicate_Do_DB',
                'Replicate_Ignore_DB',
                'Replicate_Do_Table',
                'Replicate_Ignore_Table',
                'Replicate_Wild_Do_Table',
                'Replicate_Wild_Ignore_Table',
            ];
            if (! in_array($variable, $variablesWrap)) {
                continue;
            }

            $variables[$variable]['value'] = str_replace(
                ',',
                ', ',
                $serverReplicationVariable
            );
        }

        return $this->template->render('server/replication/status_table', [
            'type' => $type,
            'is_hidden' => $isHidden,
            'has_title' => $hasTitle,
            'variables' => $variables,
        ]);
    }

    /**
     * get the correct username and hostname lengths for this MySQL server
     *
     * @return array   username length, hostname length
     */
    public function getUsernameHostnameLength()
    {
        global $dbi;

        $fieldsInfo = $dbi->getColumns('mysql', 'user');
        $usernameLength = 16;
        $hostnameLength = 41;
        foreach ($fieldsInfo as $val) {
            if ($val['Field'] === 'User') {
                strtok($val['Type'], '()');
                $v = strtok('()');
                if (Util::isInteger($v)) {
                    $usernameLength = (int) $v;
                }
            } elseif ($val['Field'] === 'Host') {
                strtok($val['Type'], '()');
                $v = strtok('()');
                if (Util::isInteger($v)) {
                    $hostnameLength = (int) $v;
                }
            }
        }

        return [
            $usernameLength,
            $hostnameLength,
        ];
    }

    /**
     * returns html code to add a replication slave user to the master
     *
     * @return string HTML code
     */
    public function getHtmlForReplicationMasterAddSlaveUser()
    {
        global $dbi;

        [
            $usernameLength,
            $hostnameLength,
        ] = $this->getUsernameHostnameLength();

        if (isset($_POST['username']) && strlen($_POST['username']) === 0) {
            $GLOBALS['pred_username'] = 'any';
        }

        $username = '';
        if (! empty($_POST['username'])) {
            $username = $GLOBALS['new_username'] ?? $_POST['username'];
        }

        $currentUser = $dbi->fetchValue('SELECT USER();');
        if (! empty($currentUser)) {
            $userHost = str_replace(
                "'",
                '',
                mb_substr(
                    $currentUser,
                    mb_strrpos($currentUser, '@') + 1
                )
            );
            if ($userHost !== 'localhost' && $userHost !== '127.0.0.1') {
                $thisHost = $userHost;
            }
        }

        // when we start editing a user, $GLOBALS['pred_hostname'] is not defined
        if (! isset($GLOBALS['pred_hostname']) && isset($_POST['hostname'])) {
            switch (mb_strtolower($_POST['hostname'])) {
                case 'localhost':
                case '127.0.0.1':
                    $GLOBALS['pred_hostname'] = 'localhost';
                    break;
                case '%':
                    $GLOBALS['pred_hostname'] = 'any';
                    break;
                default:
                    $GLOBALS['pred_hostname'] = 'userdefined';
                    break;
            }
        }

        return $this->template->render('server/replication/master_add_slave_user', [
            'username_length' => $usernameLength,
            'hostname_length' => $hostnameLength,
            'has_username' => isset($_POST['username']),
            'username' => $username,
            'hostname' => $_POST['hostname'] ?? '',
            'predefined_username' => $GLOBALS['pred_username'] ?? '',
            'predefined_hostname' => $GLOBALS['pred_hostname'] ?? '',
            'this_host' => $thisHost ?? null,
        ]);
    }

    /**
     * handle control requests
     *
     * @return void
     */
    public function handleControlRequest()
    {
        if (! isset($_POST['sr_take_action'])) {
            return;
        }

        $refresh = false;
        $result = false;
        $messageSuccess = '';
        $messageError = '';

        if (isset($_POST['slave_changemaster']) && ! $GLOBALS['cfg']['AllowArbitraryServer']) {
            $_SESSION['replication']['sr_action_status'] = 'error';
            $_SESSION['replication']['sr_action_info'] = __(
                'Connection to server is disabled, please enable'
                . ' $cfg[\'AllowArbitraryServer\'] in phpMyAdmin configuration.'
            );
        } elseif (isset($_POST['slave_changemaster'])) {
            $result = $this->handleRequestForSlaveChangeMaster();
        } elseif (isset($_POST['sr_slave_server_control'])) {
            $result = $this->handleRequestForSlaveServerControl();
            $refresh = true;

            switch ($_POST['sr_slave_action']) {
                case 'start':
                    $messageSuccess = __('Replication started successfully.');
                    $messageError = __('Error starting replication.');
                    break;
                case 'stop':
                    $messageSuccess = __('Replication stopped successfully.');
                    $messageError = __('Error stopping replication.');
                    break;
                case 'reset':
                    $messageSuccess = __('Replication resetting successfully.');
                    $messageError = __('Error resetting replication.');
                    break;
                default:
                    $messageSuccess = __('Success.');
                    $messageError = __('Error.');
                    break;
            }
        } elseif (isset($_POST['sr_slave_skip_error'])) {
            $result = $this->handleRequestForSlaveSkipError();
        }

        if ($refresh) {
            $response = Response::getInstance();
            if ($response->isAjax()) {
                $response->setRequestStatus($result);
                $response->addJSON(
                    'message',
                    $result
                    ? Message::success($messageSuccess)
                    : Message::error($messageError)
                );
            } else {
                Core::sendHeaderLocation(
                    './index.php?route=/server/replication'
                    . Url::getCommonRaw($GLOBALS['url_params'], '&')
                );
            }
        }

        unset($refresh);
    }

    /**
     * handle control requests for Slave Change Master
     *
     * @return bool
     */
    public function handleRequestForSlaveChangeMaster()
    {
        /** @var DatabaseInterface $dbi */
        global $dbi;

        $sr = [
            'username' => $dbi->escapeString($_POST['username']),
            'pma_pw' => $dbi->escapeString($_POST['pma_pw']),
            'hostname' => $dbi->escapeString($_POST['hostname']),
            'port' => (int) $dbi->escapeString($_POST['text_port']),
        ];

        $_SESSION['replication']['m_username'] = $sr['username'];
        $_SESSION['replication']['m_password'] = $sr['pma_pw'];
        $_SESSION['replication']['m_hostname'] = $sr['hostname'];
        $_SESSION['replication']['m_port']     = $sr['port'];
        $_SESSION['replication']['m_correct']  = '';
        $_SESSION['replication']['sr_action_status'] = 'error';
        $_SESSION['replication']['sr_action_info'] = __('Unknown error');

        // Attempt to connect to the new master server
        $linkToMaster = $this->replication->connectToMaster(
            $sr['username'],
            $sr['pma_pw'],
            $sr['hostname'],
            $sr['port']
        );

        if (! $linkToMaster) {
            $_SESSION['replication']['sr_action_status'] = 'error';
            $_SESSION['replication']['sr_action_info'] = sprintf(
                __('Unable to connect to master %s.'),
                htmlspecialchars($sr['hostname'])
            );
        } else {
            // Read the current master position
            $position = $this->replication->slaveBinLogMaster($linkToMaster);

            if (empty($position)) {
                $_SESSION['replication']['sr_action_status'] = 'error';
                $_SESSION['replication']['sr_action_info']
                    = __(
                        'Unable to read master log position. '
                        . 'Possible privilege problem on master.'
                    );
            } else {
                $_SESSION['replication']['m_correct']  = true;

                if (
                    ! $this->replication->slaveChangeMaster(
                        $sr['username'],
                        $sr['pma_pw'],
                        $sr['hostname'],
                        $sr['port'],
                        $position,
                        true,
                        false
                    )
                ) {
                    $_SESSION['replication']['sr_action_status'] = 'error';
                    $_SESSION['replication']['sr_action_info']
                        = __('Unable to change master!');
                } else {
                    $_SESSION['replication']['sr_action_status'] = 'success';
                    $_SESSION['replication']['sr_action_info'] = sprintf(
                        __('Master server changed successfully to %s.'),
                        htmlspecialchars($sr['hostname'])
                    );
                }
            }
        }

        return $_SESSION['replication']['sr_action_status'] === 'success';
    }

    /**
     * handle control requests for Slave Server Control
     *
     * @return bool
     */
    public function handleRequestForSlaveServerControl()
    {
        global $dbi;

        if (empty($_POST['sr_slave_control_parm'])) {
            $_POST['sr_slave_control_parm'] = null;
        }

        if ($_POST['sr_slave_action'] === 'reset') {
            $qStop = $this->replication->slaveControl('STOP', null, DatabaseInterface::CONNECT_USER);
            $qReset = $dbi->tryQuery('RESET SLAVE;');
            $qStart = $this->replication->slaveControl('START', null, DatabaseInterface::CONNECT_USER);

            $result = $qStop !== false && $qStop !== -1 &&
                $qReset !== false && $qReset !== -1 &&
                $qStart !== false && $qStart !== -1;
        } else {
            $qControl = $this->replication->slaveControl(
                $_POST['sr_slave_action'],
                $_POST['sr_slave_control_parm'],
                DatabaseInterface::CONNECT_USER
            );

            $result = $qControl !== false && $qControl !== -1;
        }

        return $result;
    }

    /**
     * handle control requests for Slave Skip Error
     *
     * @return bool
     */
    public function handleRequestForSlaveSkipError()
    {
        global $dbi;

        $count = 1;
        if (isset($_POST['sr_skip_errors_count'])) {
            $count = $_POST['sr_skip_errors_count'] * 1;
        }

        $qStop = $this->replication->slaveControl('STOP', null, DatabaseInterface::CONNECT_USER);
        $qSkip = $dbi->tryQuery(
            'SET GLOBAL SQL_SLAVE_SKIP_COUNTER = ' . $count . ';'
        );
        $qStart = $this->replication->slaveControl('START', null, DatabaseInterface::CONNECT_USER);

        return $qStop !== false && $qStop !== -1 &&
            $qSkip !== false && $qSkip !== -1 &&
            $qStart !== false && $qStart !== -1;
    }
}
