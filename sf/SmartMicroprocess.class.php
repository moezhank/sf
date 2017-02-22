<?php
/**
 *   @ClassName : SmartMicroprocess
 *   @Analyzed By : Dyan Galih Nugroho Wicaksi<dyan.galih@gmail.com>
 *   @Author By : Dyan Galih Nugroho Wicaksi<dyan.galih@gmail.com>
 *   @Version : 01
 *   @StartDate : 2016-04-15
 *   @LastUpdate : 2016-04-15
 *   @Description : Smart Micro Process
 */

class SmartMicroprocess extends Database {
    protected $mSqlFile;
    private $paramsWithValue;
    private $resultMessage;
    private $microservice;
    private $processList = array();
    private $development = false;
    private $resultData;
    private $host;
    private $uploadPath = "./upload/";
    private $imageResize;
    private $email_smtp;
    private $email_account;
    private $email_password;
    private $email_smtp_port;
    private $email_secure_type;
    private $nextProcess = "";
    private $dataTables;
    private $destruct   = true;
    private $selfSigned = false;

    private function setQuery() {
        $this->mSqlQueries["get_microprocess_by_code"] = "
      SELECT
         `microprocessId`,
         `microprocessCode`,
         `microprocessDesc`,
         `microprocessMethod`,
         `microprocessReturn`,
         `microprocessProcessNext`,
         `microprocessProcessJumpProcess`,
         `processId`,
         `processCode`,
         `processDesc`,
         `processProcess`,
         `processMethod`,
         `paramName` AS `processResult`,
         `paramTypeData` AS `processTypeOutput`,
         `microprocessProcessJoin`,
         `microprocessProcessEmpty`,
         `microprocessProcessFalseCode`,
         `microprocessProcessFalseMessage`,
         `microprocessProcessTrueCode`,
         `microprocessProcessTrueMessage`,
         `microprocessCustomeSuccessCode`,
         `microprocessCustomeSuccessMessage`,
         (SELECT `paramName` FROM `sf_microprocess_ref_param` WHERE `paramId` = `microprocessProcessKeyId` LIMIT 1) AS `keyField`,
         (SELECT paramName FROM sf_microprocess_ref_param WHERE paramId = microprocessProcessForeignId LIMIT 1) AS `keyForeign`,
         `microprocessStatus`,
         `processType`,
         IF(
            `microprocessProcessLinkId` IS NOT NULL,
            (SELECT
               `paramName`
            FROM
               sf_microprocess_ref_process
               JOIN `sf_microprocess_ref_param`
                  ON `processResultParamId` = `paramId`
            WHERE processId = `microprocessProcessLinkId`),
            NULL
         ) AS keyCode
      FROM
         `sf_microprocess`
         JOIN `sf_microprocess_process`
            ON `microprocessProcessMicroprocessId` = `microprocessId`
         JOIN `sf_microprocess_ref_process`
            ON `microprocessProcessProcessId` = `processId`
         LEFT JOIN `sf_microprocess_ref_param`
            ON `processResultParamId` = `paramId`
      WHERE
         `microprocessCode` = '%s'
      AND
         (`microprocessProcessOrder` >= '%s' OR ''='%s')
      ORDER BY microprocessProcessOrder ASC
      ";

        $this->mSqlQueries["get_input_params_child"] = "
      SELECT
         `microprocessInputId`,
         `paramName`,
         `paramTypeData`,
         IFNULL(`microprocessInputAllowNull`,`paramAllowNull`) AS `paramAllowNull`,
         `microprocessInputModel`
      FROM
         `sf_microprocess_input`
         JOIN `sf_microprocess_ref_param`
            ON `microprocessInputParamId` = `paramId`
      WHERE microprocessInputParamParentId = '%s'
      ORDER BY microprocessInputParamOrder ASC
      ";

        $this->mSqlQueries["get_input_params"] = "
      SELECT
         `microprocessInputId`,
         `paramName`,
         `paramTypeData`,
         `paramAllowNull`,
         IFNULL(`microprocessInputAllowNull`,`paramAllowNull`) AS `paramAllowNull`,
         `microprocessInputModel`
      FROM
         `sf_microprocess_input`
         JOIN `sf_microprocess_ref_param`
            ON `microprocessInputParamId` = `paramId`
      WHERE `microprocessInputProcessId` = '%s'
      AND microprocessInputParamParentId IS NULL
      ORDER BY microprocessInputParamOrder ASC
      ";

        $this->mSqlQueries["get_output_params"] = "
      SELECT
         `paramName`,
         `paramTypeData`,
         IFNULL(`microprocessOutputAllowNull`,`paramAllowNull`) AS `paramAllowNull`
      FROM
         `sf_microprocess`
         JOIN `sf_microprocess_output`
            ON `microprocessOutputMicroprocessId` = `microprocessId`
         JOIN `sf_microprocess_ref_param`
            ON `microprocessOutputParamId` = `paramId`
      WHERE
         `microprocessCode` = '%s'
      ";

        $this->mSqlQueries["check_service_access"] = "
        SELECT
            microprocessAccess,
            microprocessMicroprocessId
          FROM
            sf_microprocess
            JOIN sf_microprocess_group
              ON microprocessMicroprocessId = microprocessId 
            JOIN `sf_user_group` ON `userGroupGroupId` = `microprocessGroupId` AND `userId` = '%s'
          WHERE microprocessCode = '%s'
        ";

        $this->mSqlQueries["add_log"] = "
      INSERT INTO
         system_log
      SET
         logValue = '%s'
      ";

        $this->mSqlQueries["get_order_number"] = "
         SELECT
            microprocessProcessOrder
         FROM
            sf_microprocess_process
         WHERE microprocessProcessId = '%s'
      ";
    }

    private function getOrderNumberByProcessId($processId) {
        $result = $this->Open($this->mSqlQueries['get_order_number'], array($processId));
        if (!empty($result)) {
            return $result[0]["microprocessProcessOrder"];
        } else {
            $this->output(ResultMessage::Instance()->dataNotFound(new stdClass, array("message" => "Jump Process Not Found")));
        }
    }

    public function __construct() {
        $this->setQuery();

        if (Config::instance()->getValue("self_signed") != null) {
            $this->selfSigned = true;
        }

        if (Config::instance()->getValue("main_result") == null) {
            $this->resultMessage = "result";
        } else {
            $this->resultMessage = Config::instance()->getValue("main_result");
        }
        require_once Config::instance()->getValue("docroot") . 'sf/ResizeImage.class.php';

        parent::__construct();
        $this->paramsWithValue = $_REQUEST;
        if (isset($_SERVER["HTTP_USER_CODE"])) {
            $this->paramsWithValue = array_merge($this->paramsWithValue, array("systemKeyCode" => $_SERVER["HTTP_USER_CODE"]));
        };
        $this->paramsWithValue = array_merge($this->paramsWithValue, array("systemHost" => $_SERVER["HTTP_HOST"]));

        $this->setDefaultUser();
        if (Config::instance()->getValue("upload_path") !== null) {
            $this->uploadPath = Config::instance()->getValue("upload_path");
        }

        if (Config::instance()->getValue("next_process") !== null) {
            $this->nextProcess = Config::instance()->getValue("next_process");
        }

        if (Config::instance()->getValue("image_resize") !== null) {
            $this->imageResize = Config::instance()->getValue("image_resize");
        }

        $this->setDefaultPagingValue();

        $this->email_account     = Config::instance()->getValue("email_account");
        $this->email_smtp        = Config::instance()->getValue("email_smtp");
        $this->email_password    = Config::instance()->getValue("email_password");
        $this->email_smtp_port   = Config::instance()->getValue("email_smtp_port");
        $this->email_secure_type = Config::instance()->getValue("email_secure_type");

        #print_r($_FILES);

        #$this->setDebugOn();
    }

    private function setDefaultPagingValue() {
        if (isset($_REQUEST['draw'])) {
            $this->dataTables["draw"]                   = $_REQUEST['draw'];
            $this->paramsWithValue["systemPagingLimit"] = (integer) (($_REQUEST['length'] > 100) ? 100 : $_REQUEST['length']) * 1;
            $this->paramsWithValue["systemPagingStart"] = (integer) $_REQUEST['start'];
            $this->paramsWithValue["systemSearch"]      = $_REQUEST["search"]["value"];
        } else {
            $this->paramsWithValue["systemPagingLimit"] = (integer) Config::instance()->getValue("limit") == null ? 20 : (integer) Config::instance()->getValue("limit");
            if (empty($this->paramsWithValue["systemPage"])) {
                $this->paramsWithValue["systemPage"] = 1;
            }
            if ($this->paramsWithValue["systemPage"] < 0) {
                $this->paramsWithValue["systemPage"] = 1;
            }
            $this->paramsWithValue["systemPagingStart"] = (integer) (($this->paramsWithValue["systemPage"] - 1) * (integer) $this->paramsWithValue["systemPagingLimit"]);
        }

    }

    private function runStream($params, $data) {
        $this->destruct = false;
        require_once Config::instance()->getValue("docroot") . 'lib/smartRead/smartReadFile.class.php';
        echo SmartReadFile::getInstance()->getReadFile($this->uploadPath . $params[0], $params[0]);
        exit;
    }

    private function runParseTemplate($params, $data) {
        if (file_exists(Config::instance()->getValue("docroot") . 'lib/mustache/src/Mustache/Autoloader.php')) {
            require_once Config::instance()->getValue("docroot") . 'lib/mustache/src/Mustache/Autoloader.php';
        } elseif (file_exists(Config::instance()->getValue("docroot") . 'vendor/mustache/mustache/src/Mustache/Autoloader.php')) {
            require_once Config::instance()->getValue("docroot") . 'vendor/mustache/mustache/src/Mustache/Autoloader.php';
        } else {
            $this->output(ResultMessage::Instance()->systemError(new stdClass, array("message" => "Need Lib Mustache PHP Via composer, or clone into lib/mustache")));
        }

        $paramToParse[$params["0"]["paramName"]] = $this->paramsWithValue[$params["0"]["paramName"]];

        Mustache_Autoloader::register();
        $mustache                                      = new Mustache_Engine;
        $this->paramsWithValue[$data["processResult"]] = $mustache->render($data["processProcess"], $paramToParse);
    }

    private function sendMail($params, $data) {

        if (empty($params)) {
            $params = $this->paramsWithValue;
        }
        if (file_exists(Config::instance()->getValue("docroot") . 'lib/PHPMailer/PHPMailerAutoload.php')) {
            require_once Config::instance()->getValue("docroot") . 'lib/PHPMailer/PHPMailerAutoload.php';
        } elseif (file_exists(Config::instance()->getValue("docroot") . 'vendor/phpmailer/phpmailer/PHPMailerAutoload.php')) {
            require_once Config::instance()->getValue("docroot") . 'vendor/phpmailer/phpmailer/PHPMailerAutoload.php';
        } else {
            $this->output(ResultMessage::Instance()->systemError(new stdClass, array("message" => "Need Lib PHPMailer Via composer, or clone into lib/PHPMailer")));
        }
        $mail = new PHPMailer(true);
        $mail->setFrom($params["systemMailFromEmail"], $params["systemMailFromName"]);

        $mail->isSMTP();

        if ($this->selfSigned) {
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ),
            );
        }
        if ($this->development) {
            $mail->SMTPDebug = 4;
        } else {
            $mail->SMTPDebug = 0;
        }
        $mail->Host       = $this->email_smtp;
        $mail->SMTPAuth   = true;
        $mail->Username   = $this->email_account;
        $mail->Password   = $this->email_password;
        $mail->SMTPSecure = $this->email_secure_type;
        $mail->Port       = $this->email_smtp_port;

// Add a recipient
        $mail->addAddress($params["systemMailSentTo"]);

        $mail->isHTML(true);

        $mail->Subject = $params["systemMailSubject"];
        if (!isset($params["systemMailBody"])) {
            $mail->Body = $data["processProcess"];
        } else {
            $mail->Body = $params["systemMailBody"];
        }

        if (!$mail->send()) {
            $this->output(ResultMessage::Instance()->saveDataFailed(new stdClass, array("message" => "Process Failed")));
        } else {
            return true;
        }
    }

    private function output($message) {
        $this->resultData = $message;
        exit;
    }

    private function setDefaultUser() {
        $this->paramsWithValue["systemUserId"]  = $_SESSION["user"]["userId"];
    }

    private function checkAccessService($service) {
        $result = $this->Open($this->mSqlQueries['check_service_access'], array($_SESSION["user"]["userId"], $service));
        if (!empty($result) && $result["0"]["microprocessAccess"] == "Excluesive" && $result["0"]["microprocessMicroprocessId"] == "") {
            if (isset($_SESSION['is_login'])) {
                $this->output(ResultMessage::Instance()->forbiddenAccess(new stdClass, array("message" => "Forbidden Access")));
            } else {
                $this->output(ResultMessage::Instance()->requestDenied(new stdClass, array("message" => "Request Denied")));
            }
        }
    }

    private function getBenchmark() {
        $time_end     = getmicrotime();
        $time         = $time_end - Config::instance()->getValue("start_time");
        $endOfMemory  = memory_get_usage();
        $memory_usage = convert($endOfMemory - Config::instance()->getValue("start_memory"));

        return array("time" => $time, "memory" => $memory_usage);
    }

/**
 * Microprocess
 */

    private function uploadFile($name) {
        if (
            !isset($_FILES[$name]['error']) ||
            is_array($_FILES[$name]['error'])
        ) {
            $this->output(ResultMessage::Instance()->requestNotMatch(new stdClass, array("message" => "Upload File Not match upload")));
        }
        switch ($_FILES[$name]['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            $this->output(ResultMessage::Instance()->saveDataFailed(new stdClass, array("message" => "File Not Uploaded")));
            break;
        case UPLOAD_ERR_INI_SIZE:
            $this->output(ResultMessage::Instance()->saveDataFailed(new stdClass, array("message" => "Exceeded filesize limit.")));
            break;
        case UPLOAD_ERR_FORM_SIZE:
            $this->output(ResultMessage::Instance()->saveDataFailed(new stdClass, array("message" => "Exceeded filesize limit.")));
            break;
        default:
            $this->output(ResultMessage::Instance()->saveDataFailed(new stdClass, array("message" => "Process Failed")));
            break;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        if (false === $ext = array_search(
            $finfo->file($_FILES[$name]['tmp_name']),
            array(
                'jpg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
            ),
            true
        )) {
            $this->output(ResultMessage::Instance()->requestNotMatch(new stdClass, array("message" => "Data Type Forbidden")));
        }
        if (!file_exists($this->uploadPath)) {
            $this->output(ResultMessage::Instance()->dataNotComplete(new stdClass, array("message" => "Upload Directory Not Found")));
        }

        $this->paramsWithValue[$name] = $this->paramsWithValue["systemUserId"] . "_" . sha1_file($_FILES[$name]['tmp_name']) . '.' . $ext;

        $newFileName = sprintf($this->uploadPath . '/%s', $this->paramsWithValue[$name]);

        if (!move_uploaded_file($_FILES[$name]['tmp_name'], $newFileName)) {
            $this->output(ResultMessage::Instance()->saveDataFailed(new stdClass, array("message" => "Save Data Failed")));
        }
        if (isset($this->paramsWithValue["systemResize"])) {
            $this->resize($newFileName, $this->paramsWithValue[$name]);
        }

        return $this->paramsWithValue[$name];
    }

    private function resize($image, $name) {
        $resize = new ResizeImage($image);

        foreach ($this->imageResize as $key => $value) {
            $resize->resizeTo($value["width"], $value["height"]);
            $resize->saveImage($this->uploadPath . '/' . $value["prefix"] . $name);
        }
    }

    private function runProcess($service, $number = 0) {

        foreach ($this->microservice["process"] as $key => $value) {
            $resultParam = $this->getParamsById($value["processId"]);

            if ($this->development) {
                $this->processList[$number] = array("process" => $value["processCode"], "params" => $this->paramsWithValue);
            }

            switch ($value['processType']) {
            case 'code':
//process class
                $params = $this->checkParams($resultParam, true);
                $this->runClass($params, $value);
                break;
            case 'rest_get':
//process service
                $params = $this->checkParams($resultParam, true);
                $this->runRest($params, $value, 'GET');
                break;
            case 'rest_post':
//process service
                $params = $this->checkParams($resultParam, true);
                $this->runRest($params, $value, 'POST');
                break;
            case 'email':
                $params = $this->checkParams($resultParam, true);
                $this->sendMail($params, $value);
                break;
            case 'merge':
                $params = $this->checkParams($resultParam);
                $this->runMergeSource($params, $value);
                break;
            case 'template':
                $this->runParseTemplate($resultParam, $value);
                break;
            case 'stream':
                $params = $this->checkParams($resultParam);
                $this->runStream($params, $value);
                break;
            default:
                if (!empty($resultParam)) {
                    $params = $this->checkParams($resultParam);
                } else {
                    $params = array();
                }
                $this->runQuery($params, $value);
                break;
            }
            if ($this->development) {
                $this->processList[$number] = array_merge($this->processList[$number], array("benchmark" => $this->getBenchmark()));
            }
            $number++;

            if ($value["microprocessProcessNext"] == "stop") {
                break;
            }

            if ($value["microprocessProcessNext"] == "jumpto" && isset($this->paramsWithValue["systemJumpto"])) {
                unset($this->paramsWithValue["systemJumpto"]);
                $orderNumber = $this->getOrderNumberByProcessId($value["microprocessProcessJumpProcess"]);
                $this->getMicroServiceByCode($service, $orderNumber);
                $this->runProcess($service, $number);
                break;
            }
        }
    }

    public function process() {
        $service = isset($_GET["service"]) ? $_GET["service"] : "";
        if ($service == "") {
            $this->output(ResultMessage::Instance()->dataNotFound(new stdClass, array("message" => "no service")));
        };

        switch ($service) {
        case 'getSalt':
            $this->output(ResultMessage::Instance()->requestSuccess(array("salt" => Security::instance()->getSalt())), array("message" => "Request Salt Success"));
            break;
        case 'login':
            if (!isset($this->paramsWithValue["username"])) {
                $this->paramsWithValue["username"] = "";
            }
            if (!isset($this->paramsWithValue["password"])) {
                $this->paramsWithValue["password"] = "";
            }
            if (Security::instance()->checkLogin($this->paramsWithValue["username"], $this->paramsWithValue["password"], true)) {
                if ($this->nextProcess == "") {
                    $this->output(ResultMessage::Instance()->saveDataSuccess(new stdClass, array("message" => "Login Success")));
                } else {
                    $service = $this->nextProcess;
                }

            } else {
                $this->output(ResultMessage::Instance()->saveDataFailed(new stdClass, array("message" => "Login Failed")));
            }
            break;
        case 'logout':
            Security::instance()->doLogout();
            $this->output(ResultMessage::Instance()->saveDataSuccess(new stdClass, array("message" => "Logout Success")));
            break;
        case 'getLoginStatus':
            if (isset($_SESSION['is_login'])) {
                $this->output(ResultMessage::Instance()->requestSuccess(array("login_status" => true), array("message" => "Already Login")));
            } else {
                $this->output(ResultMessage::Instance()->requestDenied(new stdClass, array("message" => "Request Denied")));
            }
            break;
        default:
            $this->checkAccessService($service);

            $this->getMicroServiceByCode($service);
            $this->startTrans();

            if (empty($this->microservice["process"])) {
                $debug = SysLog::getInstance()->getLog("database");
                
                $this->output(ResultMessage::Instance()->dataNotFound(array("process_not_found" => $service, "debug"=>$debug)), array("message" => "Process Not Found"));
            };

            if ($this->microservice["process"][0]["microprocessStatus"] == "sanbox") {
                $this->setDebugOn();
                $this->development = true;
            };

            $this->runProcess($service);

            $log["params"] = $this->paramsWithValue;
            $log["server"] = $_SERVER;

            if (!empty($log)) {
                $this->addLog(json_encode($log));
            }

            $this->commit();

            if ($this->microservice["process"][0]["microprocessMethod"] == "open") {
                $this->sendOutputArray($service);
            } else {
                if ($this->microservice["process"][0]["microprocessCustomeSuccessCode"] == "") {
                    $this->output(ResultMessage::Instance()->saveDataSuccess(new stdClass, array("message" => "Process Success")));
                } else {
                    $this->output(ResultMessage::Instance()->formatMessage($this->microservice["process"][0]["microprocessCustomeSuccessCode"], array(), array("message" => $this->microservice["process"][0]["microprocessCustomeSuccessMessage"])));
                }
            }
            break;
        }
    }

    private function runMergeSource($values, $string) {
        $this->paramsWithValue[$string["processResult"]] = vsprintf($string["processProcess"], $values);
    }

    private function sendOutputArray($service) {
        $resultParam = $this->getOuputParams($service);
        $empty       = false;
        $params      = "";

        $output = $this->microservice["process"]["0"]["microprocessReturn"];

        if ($output == "json") {
            foreach ($resultParam as $key => $value) {
                if (empty($this->paramsWithValue[$value["paramName"]]) && $value["paramAllowNull"] == "no") {
                    $empty = true;
                } else {
                    $params[$value["paramName"]] = $this->paramsWithValue[$value["paramName"]];
                }
            }

            if ($empty && $this->microservice["process"][0]["microprocessCustomeSuccessCode"] == "") {
                $this->output(ResultMessage::Instance()->dataNotFound(new stdClass, array("message" => "Data Not Found")));
            } elseif ($this->microservice["process"][0]["microprocessCustomeSuccessCode"] == "") {
                $this->output(ResultMessage::Instance()->requestSuccess($params, array("message" => "Request Success")));
            } else {
                $this->output(ResultMessage::Instance()->formatMessage($this->microservice["process"][0]["microprocessCustomeSuccessCode"], $params, array("message" => $this->microservice["process"][0]["microprocessCustomeSuccessMessage"])));
            }
        } else {

            $outputString = "";
            foreach ($resultParam as $key => $value) {
                if (empty($this->paramsWithValue[$value["paramName"]]) && $value["paramAllowNull"] == "no") {
                    $empty = true;
                } else {
                    if ($this->paramsWithValue[$value["paramName"]] === (array) $this->paramsWithValue[$value["paramName"]]) {
                        $outputString .= json_encode($this->paramsWithValue[$value["paramName"]]);
                    } else {
                        $outputString .= $this->paramsWithValue[$value["paramName"]];
                    }
                }
            }
            $this->destruct = false;
            $debugString    = "";
            if ($this->development) {
                $this->processList[] = array("process" => "Final", "params" => $this->paramsWithValue, "benchmark" => $this->getBenchmark(), "database", SysLog::getInstance()->getLog("database"));
                $debugString         = "<br /><div>" . json_encode($this->processList) . "</div>";
            };
            die($outputString . $debugString);
        }

    }

    private function checkTypeNotArray($type, $value, $data = '') {
        if (
            ($type == 'integer' && !ctype_digit(strval($value))) ||
            ($type == 'string' && (string) $value !== $value) ||
            ($type == 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) ||
            ($type == 'url' && !filter_var($value, FILTER_VALIDATE_URL))
        ) {
            $this->output(ResultMessage::Instance()->requestNotMatch(array('value' => $value, 'type' => $type, 'data' => $data), array('message' => 'Data Type Not Match')));
        }
    }

    private function checkParams($result, $obj = false) {
        $params = array();
        if ((array) $result === $result) {
            foreach ($result as $key => $value) {

                if ($value["paramTypeData"] == 'file') {
                    $this->uploadFile($value['paramName']);
                }
                if ($value["paramTypeData"] == "multiarray" && isset($value["microprocessInputId"])) {
                    $paramsChild = $this->getParamsChild($value["microprocessInputId"]);

                    foreach ($this->paramsWithValue[$value["paramName"]] as $keyDetail => $valueDetail) {
                        foreach ($paramsChild as $keyParam => $valueParam) {
                            if ($valueParam["paramAllowNull"] == "no" && (!isset($valueDetail[$valueParam["paramName"]]) || $valueDetail[$valueParam["paramName"]] == "")) {

                                if ($this->development) {
                                    $data = array("Data Not Complete" => $valueParam, "data" => $keyDetail);
                                } else {
                                    $data = array();
                                }

                                $this->output(ResultMessage::Instance()->dataNotComplete($data, array("message" => "Data Not Complete")));
                            }
                            if ($valueParam['paramAllowNull'] == 'yes' && !isset($valueDetail[$valueParam['paramName']])) {
                                $valueDetail[$valueParam['paramName']] = '';
                            }
                            $this->checkTypeNotArray($valueParam['paramTypeData'], $valueDetail[$valueParam['paramName']], $valueDetail);
                            $params_child[] = $valueDetail[$valueParam["paramName"]];
                        }
                        $params[] = $params_child;
                        unset($params_child);
                    }
                } elseif ($value["paramAllowNull"] == "no" && (empty($this->paramsWithValue[$value["paramName"]]) || (!isset($this->paramsWithValue[$value["paramName"]]) || $this->paramsWithValue[$value["paramName"]] == ""))) {

                    if ($this->development) {
                        $data = array("Data Not Complete" => $value["paramName"]);
                    } else {
                        $data = array();
                    }

                    $this->output(ResultMessage::Instance()->dataNotComplete($data, array("message" => "Data Not Complete")));
                } else {
                    if ($value['paramAllowNull'] == 'yes' && isset($this->paramsWithValue[$value['paramName']])) {
                        $paramsWithValue = $this->paramsWithValue[$value['paramName']];
                    } elseif ($value['paramAllowNull'] == 'no') {
                        $paramsWithValue = $this->paramsWithValue[$value['paramName']];
                    } else {
                        /*
                        set default value
                         */
                        if ($value['paramTypeData'] == "integer") {
                            $paramsWithValue = 0;
                        } else {
                            $paramsWithValue = '';
                        }
                    }
                    $this->checkTypeNotArray($value['paramTypeData'], $paramsWithValue, $value['paramName']);
                    if ($obj) {
                        $params[$value['paramName']] = $paramsWithValue;
                    } else {
                        if ((array) $paramsWithValue !== $paramsWithValue) {
                            if ($value['microprocessInputModel'] == 'like_pref') {
                                $params[] = $paramsWithValue . '%';
                            } elseif ($value['microprocessInputModel'] == 'like_suf') {
                                $params[] = '%' . $paramsWithValue;
                            } elseif ($value['microprocessInputModel'] == 'like_both') {
                                $params[] = '%' . $paramsWithValue . '%';
                            } else {
                                $params[] = $paramsWithValue;
                            }
                        } else {
                            $params[] = $paramsWithValue;
                        }
                    }
                }
            }
        }

        return $params;
    }

    private function getData($param, $data) {
        $result = $this->Open($data["processProcess"], $param);
        return $result;
    }

    private function execData($param, $data) {
        $result = $this->Execute($data["processProcess"], $param);
        if ($result > 0) {
            return true;
        } else {
            $this->rollback();
            if ($this->development) {
                $data = array("process" => $data, "result" => $result);
            } else {
                $data = array();
            }
            $message = array("message" => "Process Failed");
            if ($data['process']['microprocessProcessFalseMessage'] != '') {
                $message = array("message" => $data['process']['microprocessProcessFalseMessage']);
            } else {
                $message = array("message" => "Process Failed");
            }

            $this->output(ResultMessage::Instance()->saveDataFailed($data, $message));
        }
    }

    private function runQuery($param, $data) {
        $result = array();
        if ($data["processMethod"] == "open") {
            $result     = $this->getData($param, $data);
            $typeOutput = $data["processTypeOutput"];
            $prosesJoin = $data["microprocessProcessJoin"];
            $resultKey  = $data["processResult"];
            $joinKey    = $data["keyCode"];

            if (empty($result)) {
                $message = array();
                if ($data["microprocessProcessFalseMessage"] != "") {
                    $message = array("message" => $data["microprocessProcessFalseMessage"]);
                    $this->output(ResultMessage::Instance()->formatMessage($data["microprocessProcessFalseCode"], $result, array("message" => $data["microprocessProcessFalseMessage"])));
                } else {
                    $this->output(ResultMessage::Instance()->dataNotFound());
                }
            }

            if ($typeOutput == "single") {
                $valueResult                       = array_values($result[0]);
                $this->paramsWithValue[$resultKey] = $valueResult[0];
            } elseif (!empty($prosesJoin)) {
                switch ($prosesJoin) {
                case 'child':
                    $this->paramsWithValue[$joinKey] = $this->childJoin($this->paramsWithValue[$joinKey], $result, $resultKey, $data['keyField'], $data['keyForeign']);
                    break;
                case 'right_child':
                    $this->paramsWithValue[$joinKey] = $this->childJoin($result, $this->paramsWithValue[$joinKey], $resultKey, $data['keyForeign'], $data['keyField']);
                    break;
                case 'left':
                    $this->paramsWithValue[$joinKey] = $this->sideJoin($this->paramsWithValue[$joinKey], $result, $resultKey, $data['keyField'], $data['keyForeign']);
                    break;
                case 'right':
                    $this->paramsWithValue[$joinKey] = $this->sideJoin($result, $this->paramsWithValue[$joinKey], $resultKey, $data['keyForeign'], $data['keyField']);
                    break;
                case 'outer':
                    $source                            = $this->paramsWithValue[$joinKey];
                    $this->paramsWithValue[$joinKey]   = $this->outerJoin($source, $result, $resultKey, $data['keyField'], $data['keyForeign'], 'left');
                    $this->paramsWithValue[$resultKey] = $this->outerJoin($source, $result, $resultKey, $data['keyField'], $data['keyForeign'], 'right');
                    unset($source);
                    break;
                case 'leftouter':
                    $this->paramsWithValue[$joinKey]   = $this->outerJoin($this->paramsWithValue[$joinKey], $result, $resultKey, $data['keyField'], $data['keyForeign'], 'left');
                    $this->paramsWithValue[$resultKey] = '';
                    break;
                case 'rightouter':
                    $this->paramsWithValue[$resultKey] = $this->outerJoin($this->paramsWithValue[$joinKey], $result, $resultKey, $data['keyField'], $data['keyForeign'], 'right');
                    $this->paramsWithValue[$joinKey]   = '';
                    break;
                default:
// TODO : Error message join process unavailable.
                    $this->paramsWithValue[$joinKey] = $this->innerJoin($this->paramsWithValue[$joinKey], $result, $resultKey, $data['keyField'], $data['keyForeign']);
                    break;
                }
            } elseif ($typeOutput == "array" && !empty($result)) {
                $this->paramsWithValue = array_merge($this->paramsWithValue, $result[0]);
            } else {
                $this->paramsWithValue[$resultKey] = $result;
            }

        } else {

            $checkData = array_values($param);

            if (!empty($checkData) && (array) $checkData[0] === $checkData[0]) {
                foreach ($param as $key => $value) {
                    $this->execData($value, $data);
                }
            } else {

                $this->execData($param, $data);
            }
        }
    }

    private function runClass($params, $data) {
        include Config::instance()->getValue("docroot") . $data["processProcess"];
        $objDynClass = $data["processCode"];
        $objDynObj   = new $objDynClass();
        $result      = $objDynObj->process(array("params" => $params, "data" => $data));

        if ($data["processMethod"] == "open") {
            $this->paramsWithValue[$data["processResult"]] = $result;
        } else {
            if ($result == false) {
                $this->rollback();
                $this->output(ResultMessage::Instance()->saveDataFailed(array("data" => $params), array("message" => "Process Failed")));
            }
        }
    }

    private function runRest($params, $data, $method) {
//not running for this framework
        $url = $data['processProcess'];
        Dispatcher::Instance()->restClient($url)->setGtfwJsonHeaderOn();
        if ($this->development) {
            Dispatcher::Instance()->restClient($url)->setDebugOn();
        }
        switch ($method) {
        case 'POST':
            Dispatcher::Instance()->restClient($url)->SetPost($params);
            break;

        default:
            if ((array) $params === $params) {
                $qs = '';
                foreach ($params as $key => $value) {
                    $qs .= $key . '=' . $value;
                }
                Dispatcher::Instance()->restClient($url)->SetGet($qs);
            }
            break;
        }
        $result = Dispatcher::Instance()->restClient($url)->Send();

        if ($data['processMethod'] == 'open') {
            if (isset($result['gtfwResult']['status']) && ($result['gtfwResult']['status'] == 200 || $result['gtfwResult']['status'] == 201 || $result['gtfwResult']['status'] == 404)) {
                $this->paramsWithValue[$data['processResult']] = $result['gtfwResult']['data'];
            } else {
                $this->EndTrans(false);
                $this->output(ResultMessage::Instance()->dataNotFound(new stdClass, array("message" => "Data Not Found")));
            }
        } else {
            if ($result['gtfwResult']['status'] == 200 || $result['gtfwResult']['status'] == 201) {
                $this->output(ResultMessage::Instance()->saveDataSuccess(array('data' => $params), array("message" => "Process Success")));
            } else {
                $this->EndTrans(false);
                $this->output(ResultMessage::Instance()->saveDataFailed(array('data' => $params), array("message" => "Process Failed")));
            }
        }
    }

    private function childJoin($arrayFirst, $arraySecond, $resultKey, $keyField, $foreignKey) {
        if (!array_key_exists($keyField, $arrayFirst[0])) {
            $this->output(ResultMessage::Instance()->dataNotComplete(array('Data Not Complete' => $keyField), array("message" => "Data Not Complete")));
        }
        if (!array_key_exists($foreignKey, $arraySecond[0])) {
            $this->output(ResultMessage::Instance()->dataNotComplete(array('Data Not Complete' => $foreignKey), array("message" => "Data Not Complete")));
        }
        foreach ($arrayFirst as $firstKey => $firstValue) {
            foreach ($arraySecond as $secondKey => $secondValue) {
                if ($firstValue[$keyField] == $secondValue[$foreignKey]) {
                    $arrayFirst[$firstKey][$resultKey][] = $secondValue;
                }
            }
        }
        unset($arraySecond);

        return $arrayFirst;
    }

    private function innerJoin($arrayFirst, $arraySecond, $resultKey, $keyField, $foreignKey) {
        if (!array_key_exists($keyField, $arrayFirst[0])) {
            $this->output(ResultMessage::Instance()->dataNotComplete(array('Data Not Complete' => $keyField), array("message" => "Data Not Complete")));
        }
        if (!array_key_exists($foreignKey, $arraySecond[0])) {
            $this->output(ResultMessage::Instance()->dataNotComplete(array('Data Not Complete' => $foreignKey), array("message" => "Data Not Complete")));
        }
        $number = 0;
        foreach ($arrayFirst as $firstKey => $firstValue) {
            foreach ($arraySecond as $secondKey => $secondValue) {
                if ($firstValue[$keyField] == $secondValue[$foreignKey]) {
                    $resultArray[$number] = array_merge($arrayFirst[$firstKey], $secondValue);
                    ++$number;
                }
            }
        }
        unset($arrayFirst);
        unset($arraySecond);

        return $resultArray;
    }

    private function sideJoin($arrayFirst, $arraySecond, $resultKey, $keyField, $foreignKey) {
        if (!array_key_exists($keyField, $arrayFirst[0])) {
            $this->output(ResultMessage::Instance()->dataNotComplete(array('Data Not Complete' => $keyField), array("message" => "Data Not Complete")));
        }
        if (!array_key_exists($foreignKey, $arraySecond[0])) {
            $this->output(ResultMessage::Instance()->dataNotComplete(array('Data Not Complete' => $foreignKey), array("message" => "Data Not Complete")));
        }
        $number = 0;
        foreach ($arraySecond[0] as $key => $value) {
            $nullSecond[$key] = null;
        }
        foreach ($arrayFirst as $firstKey => $firstValue) {
            $found = false;
            foreach ($arraySecond as $secondKey => $secondValue) {
                if ($firstValue[$keyField] == $secondValue[$foreignKey]) {
                    $resultArray[$number] = array_merge($arrayFirst[$firstKey], $secondValue);
                    ++$number;
                    $found = true;
                }
            }
            if (!$found) {
                $resultArray[$number] = array_merge($arrayFirst[$firstKey], $nullSecond);
                ++$number;
            }
        }
        unset($arrayFirst);
        unset($arraySecond);

        return $resultArray;
    }

    private function outerJoin($arrayFirst, $arraySecond, $resultKey, $keyField, $foreignKey, $outerSide = null) {
        if (!array_key_exists($keyField, $arrayFirst[0])) {
            $this->output(ResultMessage::Instance()->dataNotComplete(array('Data Not Complete' => $keyField), array("message" => "Data Not Complete")));
        }
        if (!array_key_exists($foreignKey, $arraySecond[0])) {
            $this->output(ResultMessage::Instance()->dataNotComplete(array('Data Not Complete' => $foreignKey), array("message" => "Data Not Complete")));
        }
        $number = 0;
        if ($outerSide == 'left' || $outerSide == null) {
            foreach ($arrayFirst as $firstKey => $firstValue) {
                $found = false;
                foreach ($arraySecond as $secondKey => $secondValue) {
                    if ($firstValue[$keyField] == $secondValue[$foreignKey]) {
                        $found = true;
                    }
                }
                if (!$found) {
                    $resultArray[$resultKey][$number] = $arrayFirst[$firstKey];
                    ++$number;
                }
            }
        }
        if ($outerSide == 'right' || $outerSide == null) {
            foreach ($arraySecond as $secondKey => $secondValue) {
                $found = false;
                foreach ($arrayFirst as $firstKey => $firstValue) {
                    if ($secondValue[$foreignKey] == $firstValue[$keyField]) {
                        $found = true;
                    }
                }
                if (!$found) {
                    $resultArray[$resultKey][$number] = $arraySecond[$secondKey];
                    ++$number;
                }
            }
        }
        unset($arrayFirst);
        unset($arraySecond);
        if ($outerSide == null) {
            return $resultArray;
        } else {
            return $resultArray[$resultKey];
        }
    }

    private function getMicroServiceByCode($service, $order = "") {
        $this->microservice["process"] = $this->Open($this->mSqlQueries['get_microprocess_by_code'], array($service, $order, $order));
    }

    private function getParamsById($idService) {
        return $this->Open($this->mSqlQueries['get_input_params'], array($idService));
    }

    private function getOuputParams($service) {
        return $this->Open($this->mSqlQueries['get_output_params'], array($service));
    }

    private function getParamsChild($id) {
        return $this->Open($this->mSqlQueries['get_input_params_child'], array($id));
    }

    private function addLog($log) {
        return $this->Execute($this->mSqlQueries['add_log'], array($log));
    }

    public function __destruct() {
        if ($this->development) {
            $this->processList[]         = array("process" => "Final", "params" => $this->paramsWithValue, "benchmark" => $this->getBenchmark(), "database", SysLog::getInstance()->getLog("database"));
            $this->resultData["process"] = $this->processList;
        };
        if (isset($this->dataTables["draw"])) {
            $this->resultData["draw"] = $this->dataTables["draw"];
            if (isset($this->paramsWithValue["recordsTotal"])) {
                $this->resultData["recordsTotal"] = $this->paramsWithValue["recordsTotal"];
            }
            if (isset($this->paramsWithValue["recordsFiltered"])) {
                $this->resultData["recordsFiltered"] = $this->paramsWithValue["recordsFiltered"];
            }
        }
        if ($this->destruct) {
            if (empty($this->resultData)) {
                $this->resultData = ResultMessage::instance()->SystemError(new stdClass, array("message" => "System Problem Please Contact Administrator"));
            }
            echo json_encode(array($this->resultMessage => $this->resultData));
        }

    }
}
?>
