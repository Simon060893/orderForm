<?php
require_once("config.php");
require_once("SuperFormula.php");
require_once("PostCalc.php");
require_once("Utils.php");
require_once("phpmailer/PHPMailerAutoload.php");

//$pswResUpda;

class Mcalc
{
    private $filepath;
    private $db;
    private $_ladasForm;
    private $_postForm;
    private $utils;
    private $tables;
    private $listOfReplace;
    private $PRO_DIR;


    function __construct($filepath = 'data/img/temp')
    {
        $this->setTables(array('mc_promo_codes', 'mc_users', 'mc_msg', 'mc_notes', 'mc_default_val', 'mc_statistic', 'mc_user_calcs'));
//        $this->setTables(array('mc_promo_codes', 'mc_users_backup', 'mc_msg_backup', 'mc_notes', 'mc_default_val'));
        $this->filepath = $filepath;
        $this->db = $this->db();
        $this->setListOfReplace(array(
            array("val" => USER_NAME, "desc" => "if set, will be replace by name of user whose be confirmed, else with empty space"),
            array("val" => NEW_PSW, "desc" => "if set, will be replace by new password of user, else with empty space"),
            array("val" => LINK_PSW_RESET, "desc" => "if set, will be replace by link where user can reset his paswword, else with empty space"),
            array("val" => UPDATE_USER_REASSON, "desc" => "if set, will be replace by value of resason wich you'll enter on confirm dialog, else with empty space")
        ));

        $source_dir = __DIR__;
        $source_dir = explode('/', $source_dir);
        array_pop($source_dir);
        array_pop($source_dir);
        $source_dir = join("/", $source_dir);
        $this->setPRODIR($source_dir);

    }

    /**
     * @return mixed
     */
    public function getPostForm()
    {
        return $this->_postForm;
    }

    /**
     * @param mixed $postForm
     */
    public function setPostForm($postForm)
    {
        $this->_postForm = $postForm;
    }

    /**
     * @return mixed
     */
    public function getPRODIR()
    {
        return $this->PRO_DIR;
    }

    /**
     * @param mixed $PRO_DIR
     */
    public function setPRODIR($PRO_DIR)
    {
        $this->PRO_DIR = $PRO_DIR;
    }


    /**
     * @return mixed
     */
    public function getUtils()
    {
        return $this->utils;
    }

    /**
     * @param mixed $utils
     */
    public function setUtils($utils)
    {
        $this->utils = $utils;
    }

    /**
     * @return SuperFormula
     */
    public function getLadasForm()
    {
        return $this->_ladasForm;
    }

    /**
     * @param SuperFormula $ladasForm
     */
    public function setLadasForm($ladasForm)
    {
        $this->_ladasForm = $ladasForm;
    }

    /**
     * @return array
     */
    public function getTables()
    {
        return $this->tables;
    }

    /**
     * @param array $tables
     */
    public function setTables($tables)
    {
        $this->tables = $tables;
    }

    /**
     * @return array
     */
    public function getListOfReplace()
    {
        return $this->listOfReplace;
    }

    /**
     * @param array $listOfReplace
     */
    public function setListOfReplace($listOfReplace)
    {
        $this->listOfReplace = $listOfReplace;
    }

    private function db()
    {
        $conn = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_NAME);
        if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);
        return $conn;
    }

    private function timezone()
    {
        $this_tz_str = date_default_timezone_get();
        $this_tz = new DateTimeZone($this_tz_str);
        $now = new DateTime("now", $this_tz);
        $offset = $this_tz->getOffset($now);
        $minutes = abs($offset);
        $hours = floor($minutes / 60);
        $prefix = $offset < 0 ? "+" : "-";
        return $prefix + $hours;
    }

    public function saveUser($user)
    {
        if (!empty($user) && !empty($user->usr_email) && !empty($user->usr_psw) && !empty($user->usr_solt)) {
            /* $usrCode = trim(htmlspecialchars($user->usr_code));
             $sql = "Select * from `{$this->tables[0]}` where `name` = '{$usrCode}' ";
             $res = $this->db->query($sql);
             $rse = $res ? $res->fetch_assoc() : false;
             if (!$res || !$rse) {
                 return array('error' => 'sorry, we can\'nt register you account');
             }*/

            $user->usr_solt = microtime();
            $sql = "INSERT INTO `{$this->tables[1]}` ";
            $keys = "(";
            $values = '(';
            foreach ($user as $key => $value) {
                if ($value != null && !($value instanceof stdClass)
                    && $key != 'usr_psw_rep' && $key != 'usr_code' &&
                    $key != 'msg' && $key != 'userAccept'
                    && $key != 'secondName' && $key != 'usr_type'
                ) {
                    if ($key == 'usr_psw') {
                        $values .= '"' . crypt(htmlspecialchars($value), htmlspecialchars($user->usr_solt)) . '", ';
                    } else {
                        $values .= '"' . htmlspecialchars($value) . '", ';
                    }
                    $keys .= '`' . htmlspecialchars($key) . '`, ';
                }
            }
            $today = date("Y-m-d H:i:s");

//            $keys .= '`created`';
//            $values .= '"' . $today . '"';

            $keys = trim($keys, ', ') . ' )';
            $values = trim($values, ', ') . ' )';
            $sql .= $keys . ' VALUES ' . $values;

//            $stmt = $this->db->prepare($sql);
            $res = $this->db->query($sql);
            $userId = $this->db->insert_id;
            if (!$res) {
                if ($this->db->errno == 1062) {
                    return array('error' => true,
                        'type' => 'such user with ' . substr($this->db->error, 15, strrpos($this->db->error, 'for') - 15) . ' already exist!!!');
                } else {
                    return array('error' => 'problem with saving new user ' . $this->db->error);
                }

            } else {
                $last_id = $this->db->insert_id;
//                $sql = "INSERT INTO `{$this->tables[4]}` (`id_user`) VALUES ({$last_id}) ";

                $_msg = $this->getMsgByCategory(USER_REQUEST);
                $data = array(
                    'fromeName' => $_msg['fromeName'],
                    'subject' => $_msg['subject'],
                    'toMsg' => $_msg['frome'],
                    'msg' => $user->msg,
                    'frome' => $user->usr_email
                );
                $dataForUser = array(
                    'fromeName' => $_msg['fromeName'],
                    'subject' => $_msg['subject'],
                    'toMsg' => $user->usr_email,
                    'msg' => $_msg['text'],
                    'frome' => $_msg['frome']
                );

                $check = $this->sendInfoOnEmail($dataForUser, 'user', 'REGISTERER', array(USER_NAME => $user->usr_name));

                if ($check && $check['success']) {
                    return $this->sendInfoOnEmail($data, 'admin', 'Thank you for requesting access. We will be activating your account once we have reviewed your request.');

                    /* $sql = "Select * from `{$this->tables[1]}` where `id_user` = '{$userId }' ";
                     $res = $this->db->query($sql);
                     if (!$res) {
                         return array('error'=>'something going wrong on registration!!!'.$sql);
                     } else {
                         $userData = ($res->fetch_assoc());
                     }


                     if ($userData['id_doc']) {
                         $sql = "Select * from `{$this->tables[1]}` where `id_user` = '{$userData['id_doc']}' ";
                         $res = $this->db->query($sql);
                         if ($res) {
                             $userData['doctor'] = ($res->fetch_assoc());
                             $userData['doctor']['usr_psw'] = null;
                             $userData['doctor']['usr_solt'] = null;
                             $userData['doctor']['created'] = null;
 //                            var_dump( $userData );exit();
                         }
                     }
 //                    var_dump($userData);
                     return array('success' => 'you have been registered',
                         'data' => $userData);*/
                }
                return array('error' => 'problem with saving new user');
            }
        } else {
            return array('error' => "could not save user");
        }

    }

    public function getUser($data)
    {
        $lgn = $data->lgn ? htmlspecialchars($data->lgn) : htmlspecialchars($data["lgn"]);
        $psw = $data->usr_psw ? htmlspecialchars($data->usr_psw) : htmlspecialchars($data["usr_psw"]);
//        var_dump($lgn,$psw,$data);
        $result = array(
            'error' => "error",
            "type" => "lgn",
            'text' => 'Username (Email) does not exist'
        );

        if (!empty($lgn) && !empty($psw)) {
            $sql = "Select * from `{$this->tables[1]}` where `usr_email` = '{$lgn}' ";

            $res = $this->db->query($sql);
            if (!$res) {
                return $result;
            } else {
                $user_lgn = ($res->fetch_assoc());
                if ($user_lgn && $user_lgn['usr_name']) {
                    if ($user_lgn['usr_active'] != 'active') {
                        return array(
                            'error' => "error",
                            "type" => "lgn",
                            'text' => 'Thank you for requesting access. We will be activating your account once we have reviewed your request.'
                        );
                    }
                    $_psw = crypt(($psw), ($user_lgn['usr_solt']));
                    if ($user_lgn['usr_psw'] == $_psw) {
                        unset($user_lgn['usr_psw']);
//                        unset($user_lgn['usr_solt']);
                        $def_val = $this->getDefVal($user_lgn);
//                        var_dump($def_val);exit();
                        $user_lgn['calc_input'] = isset($def_val["error"]) ? array() : $def_val["val"];


                        $stat = array("statistic" => array("st_logines" => 1), "user" => $user_lgn);
                        $st = $this->setStatistic(json_decode(json_encode($stat)));
                        $user_lgn['needToShowPoPUp'] = !$st['needUpdate'];
                        return array('data' => $user_lgn);

                    } else {
                        return array(
                            'error' => "error",
                            "type" => "usr_psw",
                            'text' => 'Incorrect password'
                        );
                    }
                } else {
                    return $result;
                }
            }
        } else {
            return $result;
        }
    }

    public function getUseres($data, $isAdmin)
    {
        if (empty($data) || empty($data->user) || empty($data->user->id_user)) return array("error" => "can not get accese to load users");
//        var_dump($data);exit;
        $info = $isAdmin ? $isAdmin : $this->isAdmin(($data->user));
        if ($info['error']) return $info;
        $curUser = htmlspecialchars($data->user->id_user);

        $limit = htmlspecialchars($data->limit);
        $usersMatch = htmlspecialchars($data->usersMatch);
        $countOfUsers = empty($usersMatch) || $limit == 'ALL' ? ($limit == 'ALL' || (empty($limit) && !is_numeric($limit)) ? "" : "ORDER BY `id_user` DESC LIMIT {$limit},30") : "AND  `usr_email` LIKE  '%{$usersMatch}%'";
        $results = array();


        $sql = "Select `id_user`, `usr_name`,`usr_type`,`usr_active`,`created`,`usr_email` from `{$this->tables[1]}` WHERE `usr_email`NOT IN( 'admin@iolcalc.com') AND `id_user` NOT IN('{$curUser}') {$countOfUsers}";
//        var_dump($sql);
        $res = $this->db->query($sql);
        if (!$res) {
            return array("error" => "couldn`t get users");
        } else {
            while ($row = mysqli_fetch_array($res, MYSQL_ASSOC)) {
                $results[] = $row;
            }
            return array("success" => "user was loaded ", "users" => $results);
        }
    }

    public function dropUser($data)
    {
        if (empty($data) || empty($data->user)) return array('error' => 'sorry, user did not found');

        $info = $this->isAdmin(($data->admin));
        if ($info['error']) return $info;

        $id = htmlspecialchars($data->user->usr_email);
        $update_reason = htmlspecialchars($data->reason);
        $sql = "DELETE From `{$this->tables[1]}` Where `usr_email`= \"{$id}\" ";
        $res = $this->db->query($sql);
        if (!$res) {
            return array('error' => 'could not delete user' . $sql);
        } else {
            $_msg = $this->getMsgByCategory(DROP_USER);
            $user = array(
                'frome' => $_msg['frome'],
                'fromeName' => $_msg['fromeName'],
                'toMsg' => $data->user->usr_email,
                'subject' => $_msg['subject'],
                'msg' => $_msg['text']
            );
            return $this->sendInfoOnEmail($user, 'user', 'user has been deleted', array(USER_NAME => $data->user->usr_name, UPDATE_USER_REASSON => $update_reason));
//            return array('success' => 'user was deleted');
        }
    }

    public function updateUser($data, $type)
    {

        if (empty($data) || empty($data->user)) return array('error' => "no selected user");

        $info = $this->isAdmin(($data->admin));
        if ($info['error']) return $info;

        $user = $data->user;
        $sql = "UPDATE  `{$this->tables[1]}` SET ";
        $email = htmlspecialchars($user->usr_email);
        $update_reason = htmlspecialchars($data->reason);
        $update_reason = $update_reason ? $update_reason : '';


        switch ($type) {
            case 'activateUser': {
                $usr_type = htmlspecialchars($user->usr_active);//== 'active' ? 0 : 'active';
                $curType = $usr_type == 'active' ? AUTH_USER : USER_RESTRICTIONS;
                $sql .= "`usr_active` =  \"{$usr_type}\" WHERE  `usr_email` = \"{$email}\"";
                break;
            }
            case 'setAdmin': {
                $usr_type = htmlspecialchars($user->usr_type); //== 'Admin' ? 0 : 'Admin';
                $curType = $usr_type == 'Admin' ? USER_TO_ADMIN : ADMIN_TO_USER;
                $sql .= "`usr_type` =  \"{$usr_type}\" WHERE  `usr_email` = \"{$email}\"";
                break;
            }
        }

        $_msg = $this->getMsgByCategory($curType);
        $res = $this->db->query($sql);


        $data = array(
            'fromeName' => $_msg['fromeName'],
            'subject' => $_msg['subject'],
            'toMsg' => $email,
            'msg' => $_msg['text'],
            'frome' => $_msg['frome']
        );
        if (!$res) {
            return array('error' => 'could not update user ' . $sql);

        } else {
//            return array('success' => 'update user success');
            return $this->sendInfoOnEmail($data, 'user', 'update user success', array(USER_NAME => $user->usr_name, UPDATE_USER_REASSON => $update_reason));
        }
    }

    public function updateUserInfo($data)
    {
//        var_dump(($data),empty($data->user));
        if (empty($data) || empty($data->usr_email)) return array("error" => "failed get user ");

        $user = $data;
        $sql = "UPDATE  `{$this->tables[1]}` SET ";
        $id_user = htmlspecialchars($user->id_user);
        $email = htmlspecialchars($user->usr_email);


        if (!empty($user->usr_old_psw_rep)) {
            $resul = $this->getUser((array("lgn" => $email, "usr_psw" => $user->usr_old_psw_rep)));
            $paswNedUpdate = $resul['error'] ? $resul : array("success" => 'Your password has been updated');
        } else {
            $paswNedUpdate = array("error" => 'you forgot to enter old password');
        }

        $keys = "";
        foreach ($user as $key => $value) {
            if ($value == null || ($value instanceof stdClass)
                || $key == 'usr_psw_rep' || $key == 'usr_code'
                || $key == 'msg' || $key == 'userAccept'
                || $key == 'secondName' || $key == 'firstName'
                || $key == 'usr_type' || $key == 'created'
                || $key == 'doctor' || $key == 'usr_solt'
                || $key == 'usr_email' || $key == 'id_user'
                || $key == 'usr_old_psw_rep' || $key == 'calc_input'
                || $key == 'usr_active' || $key == 'needToShowPoPUp'
            ) {

            } else {

                if ($key == 'usr_psw' && !empty($value)) {
                    if (($paswNedUpdate["success"])) {
                        $values = crypt(htmlspecialchars($value), htmlspecialchars($user->usr_solt));
                        $keys .= '`' . htmlspecialchars($key) . '` = "' . $values . '", ';
                    }
                } else {
                    $values = htmlspecialchars($value);
                    $keys .= '`' . htmlspecialchars($key) . '` = "' . $values . '", ';
                }

            }
        }
        $keys = trim($keys, ', ') . '';
        $sql .= $keys . " WHERE `id_user` ={$id_user}";
        $res = $this->db->query($sql);
        if (!$res) {
            return array("error" => "failed update user " . $sql);
        } else {
            $resr = $paswNedUpdate["error"] ? "your password was not updated, because " . $paswNedUpdate["error"] : $paswNedUpdate["success"];
            $resr = $paswNedUpdate["text"] ? "your password was not updated, because " . $paswNedUpdate["text"] : $resr;
            $isPsw = (!empty($user->usr_psw) ? $resr : false);
//            $user->usr_psw = null;
            unset($user->usr_psw);
            unset($user->usr_old_psw_rep);
            unset($user->usr_psw_rep);
            $_SESSION['user'] = $user;
            return array("success" => "user " . $email . " was updated ", "text" => $isPsw);
        }
    }

    public function updatePassword($data, $type = 'request')
    {

        if ($type == 'resetPsw') {
            foreach ($data as $key => $value) {
                if ($key != 'method') {
                    $upd_data = $this->base64url_decode($key);
                    $values = preg_split('/&/', $upd_data);
                    $sql = "UPDATE `{$this->tables[1]}` SET  ";
                    foreach ($values as $k => $val) {
                        $_d = preg_split('/=/', $val);
                        switch ($_d[0]) {
                            case'usr_email': {
                                $login = htmlspecialchars($_d[1]);
                                $where_ = " where `usr_email` = '{$login}'";
                                break;
                            }
                            case'new_psw': {
                                $_psw = htmlspecialchars($_d[1]);
                                break;
                            }
                            case'token': {
                                $token = htmlspecialchars($_d[1]);
                                break;
                            }
                        }
                    }
                    $select = "SELECT * from `{$this->tables[1]}` WHERE `usr_email`='{$login}'";
                    $res = $this->db->query($select);
//                    $responceText = 'Location: '."http://{$_SERVER['HTTP_HOST']}/".MAIN_DIR;
                    global $pswResUpda;
                    if (!$res) {
                        $pswResUpda = (array("error", "Can not find user " . $login));
                    } else {
                        $user_lgn = ($res->fetch_assoc());
                        if ($user_lgn && $user_lgn['usr_psw_status'] == 1 && $user_lgn['usr_psw_token'] == $token) {
                            $new_psw = crypt(($_psw), ($user_lgn['usr_solt']));
                            $sql .= "`usr_psw_token` = null,`usr_psw_status`= 0,`usr_psw`='" . $new_psw . "'" . $where_;
                            $res = $this->db->query($sql);
                            if (!$res) {
                                $pswResUpda = (array("error" => "something going wrong on updating user password"));
                            } else {

                                $pswResUpda = (array("success" => "Your password was reset. Please use the new password to sign in."));
                            }
                        } else {
                            $pswResUpda = (array("error" => "You can not update password. Please try again"));
                        }
                    }
                    $pswResUpda["url"] = "http://{$_SERVER['HTTP_HOST']}/" . MAIN_DIR . "sign_in";

                    include('../partials/psw_update_responce.php');
                    exit();
                }
            }

//            if(empty($data)  || empty($data->psw))return array("error"=>"you need to enter new password");

        } else {
            if (empty($data) || empty($data->login)) return array("error" => "you need to enter username");
            $lgn = htmlspecialchars($data->login);
            $sql = "Select * from `{$this->tables[1]}` where `usr_email` = '{$lgn}' ";
            $res = $this->db->query($sql);
            if (!$res) {
                return array("error" => "can not find user");
            } else {
                $user_lgn = ($res->fetch_assoc());
                if ($user_lgn && $user_lgn['usr_email'] == $lgn) {
                    if ($user_lgn['usr_active'] == 'active') {
                        $_msg = $this->getMsgByCategory(RESET_PSW);
                        $user = array(
                            'frome' => $_msg['frome'],
                            'fromeName' => $_msg['fromeName'],
                            'toMsg' => $lgn,
                            'subject' => $_msg['subject'],
                            'msg' => $_msg['text']
                        );
                        $value = $this->generateUniqString();
                        $token = mt_rand(10000, 99999);
                        $encode_link = $this->base64url_encode("new_psw=" . $value . "&usr_email=" . $lgn . "&token=" . $token);
                        $link = "http://{$_SERVER['HTTP_HOST']}/" . MAIN_DIR . MAIN_ROUTER . "?method=resetPsw&" . $encode_link;
//                        $token = substr($encode_link,strlen($encode_link)/2);

//                        $decode_link =$this->base64url_decode($link);
//                       $new_psw = crypt(htmlspecialchars($value), htmlspecialchars($user_lgn['usr_solt'])) ;
                        $sql = "UPDATE `{$this->tables[1]}` SET `usr_psw_token`=\"{$token}\",`usr_psw_status`= 1 where `usr_email` = '{$lgn}' ";
                        $res = $this->db->query($sql);
                        if (!$res) {
                            return array("error" => "coudn't change password");
                        } else {

                            return $this->sendInfoOnEmail($user, 'user', "We have emailed you a new, temporary password.", array(USER_NAME => $user_lgn['usr_name'], NEW_PSW => $value, LINK_PSW_RESET => $link));
                        }
                    } else {
                        return array("error" => "sorry, we can not update your password, until your account not acivated");
                    }
                } else {
                    return array("error" => "you need to register before edit password");
                }
            }
        }
    }

    public function promoCode($data, $type)
    {

//        var_dump($data->user);exit();
        $info = $this->isAdmin($data->code->user);
        if ($info['error']) return $info;

        if (empty($data) || empty($data->code)) {
            return array('error' => 'your code is empty');
        }
        $myCode = trim(htmlspecialchars($data->code->name));
        $id = intval(htmlspecialchars($data->code->id_code));
//        var_dump($id);
        if ($type == 'save' || $type == 'update') {
//            $sql = "INSERT INTO `{$this->tables[0]}` (`id_code` ,`name`) VALUES ( NULL ,\"{$myCode}\") ON DUPLICATE KEY UPDATE `id_code`={$id},`name`=\"{$myCode}\"";
            if ($type == 'save') {
                $sql = "INSERT INTO `{$this->tables[0]}` (`id_code` ,`name`) VALUES ( NULL ,\"{$myCode}\")";
            } else {
                $sql = "UPDATE  `{$this->tables[0]}` set `name` = \"{$myCode}\" WHERE `id_code` = {$id}";
            }
        } else {
            $sql = "DELETE From `{$this->tables[0]}` Where `id_code`={$id}";
        }
        $res = $this->db->query($sql);
        if (!$res) {
            return array('error' => 'could not save code' . $sql);
        } else {
            $ret = array('success' => 'the code was ' . $type . "d");
            if ($type == 'save') {
                $ret['id_code'] = $this->db->insert_id;
            }
            return $ret;
        }
    }

    private function getPromoCodeById($id)
    {
        if (empty($id)) return array("error" => "can not get invite code");
        $od_code = htmlspecialchars($id);
        $sql = "Select * from `{$this->tables[0]}` WHERE `id_code`='{$od_code}'";
        $res = $this->db->query($sql);
        if (!$res) {
            return array("error" => "can not get invite code" . $sql);
        } else {
            return mysqli_fetch_array($res, MYSQL_ASSOC);
        }
    }

    public function messages($data, $type)
    {


        if (empty($data) || empty($data->msg)) return array("error" => "nothing to update!!!");

        $info = $this->isAdmin(($data->user));
        if ($info['error']) return $info;


        $_msg = ($data->msg);
        $sql = false;
        $text = false;
        switch ($type) {
            case 'dropMsg': {
                $sql = "DELETE From `{$this->tables[2]}` Where `id`={($_msg->id)}";
                $text = 'deleted';

                break;
            }
            case 'editMsg': {

                $sql = "UPDATE  `{$this->tables[2]}` SET ";
                $text = 'updated';
                foreach ($_msg as $key => $value) {
                    if ($value != null && !($value instanceof stdClass) && $key != 'id') {
                        $sql .= '`' . ($key) . '` = "' . ($value) . '", ';
                    }
                }
                $sql = trim($sql, ', ') . ' ';
                $sql .= 'WHERE `id` =' . ($_msg->id);
                break;
            }
        }
        if (!$sql) return array("error" => "nothing to action!!!");
        $res = $this->db->query($sql);
        if (!$res) {
            return array('error' => 'could not ' . $text . ' message');
        } else {
            return array('success' => 'message was ' . $text);
        }
    }

    public function getSetData($data)
    {
        if (empty($data) || empty($data->user)) {
            return array('error' => 'sorry');
        }
        $info = $this->isAdmin(($data->user));
        if ($info['error']) return $info;
        $results = array(
            'codes' => array(),
            'msgs' => array(),
            'users' => array()
        );

        /*  $curUser = htmlspecialchars($data->user->id_user);
  
          $sql = "Select `id_user`, `usr_name`,`usr_type`,`usr_active`,`created`,`usr_email` from `{$this->tables[1]}` WHERE `usr_email`NOT IN( 'admin@iolcalc.com') AND `id_user` NOT IN('{$curUser}')";
          $res = $this->db->query($sql);
          while ($row = mysqli_fetch_array($res, MYSQL_ASSOC)) {
              $results['users'][] = $row;
          }*/
        $users = $this->getUseres($data, $info);
        if (!isset($users['error'])) {
            $results['users'] = $users['users'];
        };

        /* $sql = "Select * from `{$this->tables[0]}`";
         $res = $this->db->query($sql);
         while ($row = mysqli_fetch_array($res, MYSQL_ASSOC)) {
             $results['codes'][] = $row;
         }*/

        $sql = "Select * from `{$this->tables[2]}`";
        $res = $this->db->query($sql);
        if ($res) {
            while ($row = mysqli_fetch_array($res, MYSQL_ASSOC)) {
                $results['msgs'][] = $row;
            }
        }
        //return array("error" => "couldn't get messages");

        /*$sql = "Select * from `{$this->tables[3]}` WHERE `note_status`='need_confirm'";
        $res = $this->db->query($sql);
        while ($row = mysqli_fetch_array($res, MYSQL_ASSOC)) {
            $results['notes'][] = $row;
        }*/
        $_backUpFiles = $this->getFilesBackUp($data);
        if ($_backUpFiles["success"]) {
            $results['backFiles'] = $_backUpFiles["files"];
        }

        $results['info'] = $this->getListOfReplace();
        $results['uri'] = MAIN_DIR;
        return array('data' => $results);
    }

    public function reqAccess($data)
    {
        if (empty($data) || empty($data->usr_email)) {
            return array('error', 'you forgot to set your email');
        }

        $_msg = $this->getMsgByCategory(SECRET_CODE);
        $email = $_msg['frome'];
        $admin = array(
            'frome' => $data->usr_email,
            'fromeName' => $data->usr_name,
            'toMsg' => $email,
            'subject' => $_msg['subject'],
            'msg' => $data->msg,

        );

        $usr_email = htmlspecialchars($data->usr_email);
        $usr_name = htmlspecialchars($data->usr_name);

        $sql = "INSERT INTO `{$this->tables[3]}` (`usr_email`,`user_name`)VALUES('{$usr_email}','{$usr_name}') ";
        $res = $this->db->query($sql);
        if (!$res) {
            if ($this->db->errno == 1062) {
                return array('error' => 'such user with ' . substr($this->db->error, 15, strrpos($this->db->error, 'for') - 15) . ' already has taken the request!!!');
            } else {
                return array('error' => 'some problems with server');
            }
        } else {
            return $this->sendInfoOnEmail($admin, 'admin', 'your request to sign up has been received');
        }


    }

    /*--------------------------------default values------------*/
    public function defValEdit($data)
    {
        $user = $this->isAuth();
        if (isset($user['error'])) return $user['error'];

        if (empty($data) || empty($data->calc_input)) {
            return array('error' => 'nothing to update');
        }
        $def_val = $this->getDefVal($user);
        if ($def_val["error"]) return $def_val["error"];
        $needInsert = !isset($def_val["val"]["id_user"]);

        $sql = $needInsert ? "INSERT INTO `{$this->tables[4]}`" : "UPDATE  `{$this->tables[4]}` SET ";
        $upDat = "";
        $keys = "(";
        $values = '(';
        foreach ($data->calc_input as $key => $value) {
            if (!($value instanceof stdClass) && $key != 'id_user' && $key != 'id_val') {
                if ($needInsert) {
                    $values .= '"' . (htmlspecialchars($value)) . '", ';
                    $keys .= '`' . htmlspecialchars($key) . '`, ';
                } else {
                    $upDat .= '`' . htmlspecialchars($key) . '` = ' . ($value == null && !is_numeric($value) ? 'NULL,' : '"' . (htmlspecialchars($value)) . '", ');
                }
            }
        }
        if ($needInsert) {
            $values .= '"' . htmlspecialchars($user->id_user) . '")';
            $keys .= '`id_user` )';
//            $keys = trim($keys, ', ') . ' )';
//            $values = trim($values, ', ') . ' )';
            $sql .= $keys . ' VALUES ' . $values;
        } else {
            if (strlen($upDat) == 0) return array('error' => 'it seemes like you forgot to select fields to update');
            $upDat = trim($upDat, ', ') . ' ';
            $idVal = htmlspecialchars($data->calc_input->id_val);
            $sql .= $upDat . ' WHERE `id_val` =' . $idVal;
        }

        $res = $this->db->query($sql);

        if (!$res) {
            return array('error' => 'could not update default values');
        } else {
            $lastInsrt = $needInsert ? $this->db->insert_id : -1;
            if ($_SESSION['user']) {
                if ($needInsert) $data->calc_input->id_val = $lastInsrt;
                if ($_SESSION['user'] instanceof stdClass) {
                    $_SESSION['user']->calc_input = $data->calc_input ? $data->calc_input : array();
                } else {
                    $_SESSION['user']['calc_input'] = $data->calc_input ? $data->calc_input : array();
                }


            }
            return array('success' => 'Default values were updated', "val" => $lastInsrt);
        }

    }

    public function getDefVal($data)
    {
//        $data = json_encode($data);
        $eml = $data instanceof stdClass ? $data->usr_email : $data['usr_email'];
        if (empty($data) || empty($eml)) {
            return array('error' => 'could not get default values');
        }
        $id = $data instanceof stdClass ? $data->id_user : $data['id_user'];
        $sql = "SELECT * from  `{$this->tables[4]}` WHERE `id_user` ={$id}";
        $res = $this->db->query($sql);


        if (!$res) {
            return array('error' => 'could not get default values' . $sql);
        } else {
            $def_val = ($res->fetch_assoc());
            return array('success' => 'default values for ' . $eml . " was updated", "val" => $def_val);
        }
    }

    /*--------------------------------notes------------*/

    public function notesEdit($data, $type)
    {
        if (empty($data) || empty($data->usr_email)) {
            return array('error' => 'could not confirm user');
        }
        $info = $this->isAdmin(($data->user));
        if ($info['error']) return $info;

        $sql = "UPDATE  `{$this->tables[3]}` SET ";
        foreach ($data as $key => $value) {
            if ($value != null && !($value instanceof stdClass) && $key != 'user' && $key != 'id_note') {
                $sql .= '`' . htmlspecialchars($key) . '` = "' . htmlspecialchars($value) . '", ';
            }
        }
        $sql = trim($sql, ', ') . ' ';
        $sql .= 'WHERE `id_note` =' . ($data->id_note);
        $res = $this->db->query($sql);

        if (!$res) {
            return array('error' => 'could not confirm user' . $sql);
        } else {

            $_promo_code = $this->getPromoCodeById($data->id_code);
            $_msg = $this->getMsgByCategory(SECRET_CODE);

            if ($_promo_code['error']) return $_promo_code;
            if ($_msg['error']) return $_msg;

            $confirm = ($type == 'acceptNote' ? $_msg['text'] : 'Sorry, we can not give you access');
            $user = array(
                'frome' => $_msg['frome'],
                'fromeName' => $_msg['fromeName'],
                'toMsg' => $data->usr_email,
                'subject' => $_msg['subject'],
                'msg' => $confirm
            );
            return $this->sendInfoOnEmail($user, 'user', 'user ' . $data->usr_email . ' has been confirmed!!!',
                array(
                    'promo_code' => $_promo_code['name'],
                    'user_name' => $data->user_name
                )
            );
        }

    }

    /*--------------------------------calculations history for surgeoun------------*/
    private function saveCalculations($result, $data)
    {
        $user = $this->isAuth();
        if (empty($result) || empty($result['calc']) || empty($result['calc']['iolRef']) || empty($data) || empty($data->patient) || isset($user['error'])) return;//var_dump($result, $data);

        $calc_input = '';
        $calc_result = '';
        $id_user = $user['id_user'];
        $patient_name = $data->patient->name;
        $patient_id = $data->patient->id;
        $patient_eye = $data->patient->eye;

        $calc_iol = 'IOLS: ';
        $calc_ref = 'REFS: ';
        $calc_input .= 'Axial Length = ' . ($data->axialLength) . ', ';
        $calc_input .= 'K1 = ' . ($data->K1) . ', ';
        $calc_input .= 'K2 = ' . ($data->K2) . ', ';
        $calc_input .= 'KIndex = ' . ($data->KIndex) . ', ';
        $calc_input .= 'Optical ACD = ' . ($data->ACD) . ', ';
        $calc_input .= 'A-Constant = ' . ($data->AConstant) . ', ';
        $calc_input .= 'Target Refraction = ' . ($data->Rfrctn) . ', ';

        for ($i = 0; $i < count($result['calc']['iolRef']); $i++) {
            $calc_iol .= $result['calc']['iolRef'][$i]['iol'] . ', ';
            $calc_ref .= $result['calc']['iolRef'][$i]['ref'] . ', ';
        }

        $calc_result .= $calc_iol . $calc_ref;

        $query = "INSERT into " . $this->tables[6] . "(`id_user`,`patient_name`,`patient_id`,`calc_input`,`calc_result`," .
            "`calc_eye`)VALUES('{$id_user}','{$patient_name}','{$patient_id}','{$calc_input}','{$calc_result}','{$patient_eye}')";

        $res = $this->db->query($query);
        if (!$res) {
            //return  var_dump($query);
        }
    }

    public function getCalculations()
    {
        $user = $this->isAuth();
        if (isset($user['error'])) return $user['error'];

        $query = "SELECT * FROM " . $this->tables[6] . ' WHERE `id_user` = ' . $user['id_user'] . ' order by `created`';

        $res = $this->db->query($query);
        if (!$res) {
            return array("error" => "Couldn`t get any calculations" );
        } else {
            $results = array();
            while ($row = mysqli_fetch_array($res, MYSQL_ASSOC)) {
                $results[] = $row;
            }
            return array("data" => $results);
        }
    }

    public function editCalculations($edit)
    {
        $user = $this->isAuth();
//        if (isset($user['error']) || empty($edit) || empty($edit->id_calc)) return $user['error'];
        if (isset($user['error'])) return $user['error'];
        if (empty($edit) || empty($edit->id_calc)) return array("error" => "edit nothing!");


        $sql = 'UPDATE  ' . $this->tables[6] . ' Set ';
        foreach ($edit as $k => $val) {
            if (preg_match('/^id/', $k)) {

            } else {
                $sql .= $k . " = '" . $val . "',";
            }
        }

        $sql = trim($sql, ', ') . ' ';
        $sql .= 'WHERE `id_calc` =' . ($edit->id_calc) . ' AND `id_user` = ' . $user['id_user'];
        $res = $this->db->query($sql);


        if (!$res) {
            return array("error" => "could`nt update calc ", "msg" => $sql);
        } else {
            return array("success" => " update calc ");
        }
    }


    /*--------------------------------calcs------------*/
    public function _plot($data)
    {
        if (empty($data)) return array("error" => "nothing selected");
        ini_set("precision", 16);
        $stat = array("statistic" => array("st_calcs" => 1));
        $this->setStatistic(json_decode(json_encode($stat)));
        $result = array("error" => "calculator didn't selected");;
        switch ($data->type) {
            case "homeCalculate": {
                $result = $this->homePlot($data);
                break;
            }
            case "postCalculate": {
                return $this->postPlot($data);
                break;
            }
        }

        $this->saveCalculations($result, $data);
        return $result;
    }


    private function iolRefCreate($K3, $AL, $Ref3, $needInputACD)
    {
        $result = array();
        $z = array();
        $delta = 0.5;
        $_r = 2;
        $_tLS = $this->getLadasForm();
        $_utls = $this->getUtils();

        //data for ref
        $z[3] = floatval(($_tLS->_SUPERFORMULA($K3, $AL, $Ref3)));
        $z[2] = floatval(round(((floor(2 * $z[3])) / 2), $_r + 10));
        $z[4] = floatval(round(((ceil(2 * $z[3])) / 2), $_r + 10));
        $ex = $z[3] - $z[2];
        $ne = $z[4] - $z[3];
        $abss = 0.001;
        if ($ex <= $abss || $ne <= $abss) {
            $z[2] = ($z[3]) - $delta;
            $z[4] = ($z[3]) + $delta;
        } else {
            $z[2] = (floor(2 * $z[3])) / 2;
            $z[4] = (ceil(2 * $z[3])) / 2;

        }

        $z[1] = $z[2] - $delta;
        $z[5] = $z[4] + $delta;
        $z[0] = $z[1] - $delta;
        $z[6] = $z[5] + $delta;

        for ($i = 0; $i < count($z); $i++) {
            $results = 0;
            if ($i == 3) {
                $results = round($Ref3, $_r);
            } else {
                try {
                    $callback = function ($r) use ($K3, $AL, $z, $i, $_tLS) {
                        $res = (($_tLS->_SUPERFORMULA($K3, $AL, $r) - $z[$i]));
                        return $res;
                    };

                    $results = round($_utls->fzero($callback, 1), $_r);
                } catch (Exception $e) {
                    $results = round(floatval($e->getMessage()), 2);
                }
            }
            if ($z[$i] < 0 && !($needInputACD == 'yeas')) return false;
            $result[] = array("iol" => round($z[$i], $_r), "ref" => $results, "middle" => ($i == 3));
        }
        return $result;
    }

    private function validatenputFields($inputes)
    {
        $_d = $inputes;
        $calcSubmit = "calcFormSubmit";
        $notReq = "none";
        $calcSelect = array("Myopic", 'Hyperopic', 'RK');
        $calc_data = array();
        $resp = array("error" => "you forgot to input the ");
        $fields = array(
            "preRfrctn",
            "preSph",
            "preCyl",
            "preVertex",
            "preK1",
            "preK2",

            "postSph",
            "postCyl_D",
            "postVertex",
            "postEyeSysEffRP",
            "postTomey_ACCP",
            "postGalilei_TCP",
            "postVersion",
            "postVersionS",
            "postAtlasZone",
            "postPentacam_zone",
            "postAtlas_ring_fst",
            "postAtlas_ring_scd",
            "postAtlas_ring_thd",
            "postAtlas_ring_fhd",
            "postNet_Power",
            "postPosterior_Power",
            "postCentral_Power",

            "optK1",
            "optK2",
            "optinput_KIndex",
            "optDevice_Index",
            "optAL",
            "optACD",
            "optLens_Thick",
            "optWTW",
            "optA_const",
            "optSF",
            "optHaigis_a_f",
            "optHaigis_a_s",
            "optHaigis_a_t",
            "Pics",

            "octEffrp",
            "octACP",
            "octAtlf",
            "octAtls",
            "octAtlt",
            "octAtlfth",
            "octKm",
            "octCT_MIN",
            "octNCP",
            "octPp",
            "octCCT",
            "octRef"
        );
        $_const = array(1.3375,
            array($fields[34] => 0.4, $fields[35] => 0.1, $fields[8] => 12.5, $fields[3] => 12.5, $fields[4] => 43.86, $fields[5] => 43.86)
        );
        $calcsFields = array(
            "homeCalculate" => array(
                array("n" => 'K1', "l" => "K1", "min" => 35, "max" => 55),
                array("n" => 'K2', "l" => "K2", "min" => 35, "max" => 55),
                array("n" => 'axialLength', "l" => "Axial Length", "min" => 15, "max" => 35),
                array("n" => 'AConstant', "l" => "A-Constant", "min" => 110, "max" => 120),
                array("n" => 'Rfrctn', "l" => "Target Refraction", "min" => -10, "max" => 10),
                array("n" => 'ACD', "l" => "Optical ACD", "min" => 0, "max" => 5),
                array("n" => 'KIndex', "l" => "K Index", "min" => 0, "max" => 2),
                array("n" => 'AD', "l" => "AD", "min" => -10, "max" => 10),
                array("n" => 'CCT', "l" => "CCT", "min" => -1000, "max" => 1000),
                array("n" => 'needInputACD', "min" => 'none')
            ),
            "postCalculate" => array(
                array("n" => $fields[23], "l" => "K1(D)", "min" => 35, "max" => 55, "r" => $calcSubmit),
                array("n" => $fields[24], "l" => "K2(D)", "min" => 35, "max" => 55, "r" => $calcSubmit),
                array("n" => $fields[25], "l" => "Device Keratometric Index (n)", "min" => 0, "max" => 2, "r" => $notReq),
                array("n" => $fields[27], "l" => "AL(mm)", "min" => 15, "max" => 35, "r" => $calcSubmit),
                array("n" => $fields[28], "l" => "ACD(mm)", "min" => 0, "max" => 5, "r" => $calcSubmit),
                array("n" => $fields[29], "l" => "Lens Thick (mm)", "min" => 0, "max" => 130, "r" => $calcSubmit),
                array("n" => $fields[30], "l" => "WTW (mm)", "min" => 0, "max" => 130, "r" => $calcSubmit),
                array("n" => $fields[31], "l" => "A-const(SRK/T)", "min" => 110, "max" => 120, "r" => $notReq),
                array("n" => $fields[32], "l" => "SF(Holladay1))", "min" => -10, "max" => 10, "r" => $calcSubmit),
                array("n" => $fields[33], "l" => "Haigis a0 (If empty, converted value is used)", "min" => -1, "max" => 1, "r" => $calcSubmit),
                array("n" => $fields[34], "l" => "Haigis a1 (If empty, 0.4 is used)", "min" => 0, "max" => 130, "r" => $notReq),
                array("n" => $fields[35], "l" => "Haigis a2 (If empty, 0.1 is used)", "min" => 0, "max" => 130, "r" => $notReq),
                array("n" => $fields[36], "l" => "Myopic or Hyperopic", "min" => "none", "max" => 130, "r" => $calcSubmit)
            ),
        );
        $postArr = array(
            "preonic" => array(
                array("n" => $fields[0], "l" => "Target Ref (D)", "min" => -10, "max" => 10),
                array("n" => $fields[1], "l" => "Sph(D)", "min" => 0, "max" => 130),
                array("n" => $fields[2], "l" => "Cyl(D)*", "min" => 0, "max" => 130),
                array("n" => $fields[3], "l" => "Vertex (If empty, 12.5 mm is used)", "min" => 0, "max" => 130),
                array("n" => $fields[4], "l" => "K1(D)", "min" => 35, "max" => 55),
                array("n" => $fields[5], "l" => "K2(D)", "min" => 35, "max" => 55),

                array("n" => $fields[6], "l" => "Sph(D)", "min" => 0, "max" => 130),
                array("n" => $fields[7], "l" => "Cyl(D)*", "min" => 0, "max" => 130),
                array("n" => $fields[8], "l" => "Vertex (If empty, 12.5 mm is used)", "min" => 0, "max" => 130),
                array("n" => $fields[9], "l" => "EyeSys EffRP", "min" => 0, "max" => 130),
                array("n" => $fields[10], "l" => "Tomey ACCP#Nidek ACP/APP", "h" => $calcSelect[1], "min" => 0, "max" => 130),
                array("n" => $fields[11], "l" => "Galilei TCP", "h" => $calcSelect[1], "min" => 0, "max" => 130),
                array("n" => $fields[13], "l" => "Version", "h" => $calcSelect[1], "min" => 'none'),
                array("n" => $fields[14], "l" => "Atlas 9000 4mm zone", "h" => $calcSelect[1], "min" => 0, "max" => 130),
                array("n" => $fields[15], "l" => "Pentacam TNP_Apex_4.0 mm Zone", "h" => $calcSelect[1], "min" => 0, "max" => 130),
                array("n" => $fields[16], "l" => "Atlas Ring Value 0mm", "min" => 0, "max" => 130),
                array("n" => $fields[17], "l" => "Atlas Ring Value 1mm", "min" => 0, "max" => 130),
                array("n" => $fields[18], "l" => "Atlas Ring Value 2mm", "min" => 0, "max" => 130),
                array("n" => $fields[19], "l" => "Atlas Ring Value 3mm", "min" => 0, "max" => 130),
                array("n" => $fields[20], "l" => "Net Corneal Power", "min" => 0, "max" => 130),
                array("n" => $fields[21], "l" => "Posterior Corneal Power", "min" => -10, "max" => 10),
                array("n" => $fields[22], "l" => "Central Corneal Thickness", "min" => -1000, "max" => 1000),
            ),
            "nic" => array(
                array("n" => $fields[37], "l" => "EyeSys EffRP", "min" => 0, "max" => 130),
                array("n" => $fields[38], "l" => "Average Central Power", "min" => 0, "max" => 130),
                array("n" => $fields[39], "l" => "Atlas  1mm", "min" => 0, "max" => 130),
                array("n" => $fields[40], "l" => "Atlas  2mm", "min" => 0, "max" => 130),
                array("n" => $fields[41], "l" => "Atlas  3mm", "min" => 0, "max" => 130),
                array("n" => $fields[42], "l" => "Atlas  4mm", "min" => 0, "max" => 130),
                array("n" => $fields[43], "l" => "Pentacam", "min" => 0, "max" => 130),
                array("n" => $fields[44], "l" => "CT_MIN**", "min" => 400, "max" => 1000),
                array("n" => $fields[45], "l" => "OCT (RTVue or Avanti XR)   Net Corneal Power", "min" => 0, "max" => 130),
                array("n" => $fields[46], "l" => "Posterior Corneal Power", "min" => -10, "max" => 10),
                array("n" => $fields[47], "l" => "Central Corneal Thickness", "min" => -1000, "max" => 1000),
                array("n" => $fields[48], "l" => "Target Ref (D)", "min" => -10, "max" => 10)
            )
        );
        $_formVal = $calcsFields[$_d->type];
        $postCalc = $_d->type == 'postCalculate';
        $curPic = htmlspecialchars($_d->$fields[36]);
        $isHyp = $postCalc && $curPic == $calcSelect[1];
        if ($postCalc) $_formVal = array_merge($postArr[($postCalc && $curPic == $calcSelect[2] ? "nic" : "preonic")], $_formVal);


        $res = "tesdt";
        for ($i = 0; $i < count($_formVal); $i++) {
            $_inp = $_formVal[$i];
            $fNam = $_inp['n'];
            $cur_val = htmlspecialchars($_d->$fNam);
            $min = $_inp['min'];
            $max = $_inp['max'];

            if (!empty($cur_val) && isset($_d->$fNam) && is_numeric($min) && ($min > $cur_val || $cur_val > $max)) return array("error" => "The  {$_inp['l']} must be between {$min} and {$max}");

            if ($fNam == 'needInputACD' || $fNam == $fields[36] || $fNam == $fields[31] || $fNam == $fields[29] || $fNam == $fields[30] || $fNam == $fields[11] || $fNam == $fields[15]) {
                $calc_data[$fNam] = ($cur_val);
            } else if ($fNam == "AD" || $fNam == "CCT" ||
                ($isHyp && ($fNam == $fields[10] || $fNam == $fields[11] || $fNam == $fields[12] || $fNam == $fields[13] ||
                        $fNam == $fields[14] || $fNam == $fields[15]))
            ) {

                //nothing do
            } else if ($fNam == 'KIndex' || $fNam == $fields[25]) {
                $calc_data[$fNam] = empty($cur_val) || !is_numeric($cur_val) ? $_const[0] : floatval($cur_val);
            } else if ($fNam == 'ACD') {
                if (!empty($_d->$_formVal[9]['n'])) {
                    if (empty($cur_val) && !is_numeric($cur_val)) {
                        return array("error" => "An ACD value is required for the provided axial length", "ACDneedToBeRequred" => "yeas");
                    } else {
                        $calc_data[$fNam] = $cur_val;
                    }

                } else {
                    $AD = htmlspecialchars(floatval($_d->$_formVal[7]['n']));
                    $CCT = htmlspecialchars(floatval($_d->$_formVal[8]['n']));
                    //$calc_data[$_formVal[$i]] = ($t->getSF() + 3.595) / 0.9704;
                    $calc_data[$fNam] = (empty($AD) || empty($CCT)) ? $cur_val : $AD + ($CCT / 1000);
                }
            } else if ($fNam == $fields[34] || $fNam == $fields[35] || $fNam == $fields[8] || $fNam == $fields[3] || $fNam == $fields[4] || $fNam == $fields[5]) {
                $calc_data[$fNam] = !isset($_d->$fNam) || empty($cur_val) || !is_numeric($cur_val) ? $_const[1][$fNam] : floatval($cur_val);

            } else if (!isset($_d->$fNam) || empty($cur_val) && !is_numeric($cur_val)) {
                $resp['error'] .= $_inp['l'];
                return $resp;
            } else {
                $calc_data[$fNam] = floatval($cur_val);
            }
        }
//        var_dump($calc_data);exit();
        return array("calc_data" => $calc_data, "formVal" => $_formVal);
    }

    private function homePlot($data)
    {
        $_d = $data;
        $_const = array(1.3375);

        $valid = $this->validatenputFields($data);
        if ($valid['error']) return $valid;

        $calc_data = $valid['calc_data'];
        $_formVal = $valid['formVal'];
//        $t = $this->getLadasForm();
//        $ut = $this->getUtils();
        if (empty($t) || empty($ut)) {
            $this->setLadasForm(new SuperFormula());
            $this->setUtils(new Utils());
        }
        $t = $this->getLadasForm();

        $K3 = ((($calc_data[$_formVal[0]['n']]) + ($calc_data[$_formVal[1]['n']])) / 2);
        $AdjustedInputK3 = ($K3 * ($_const[0] - 1)) / (($calc_data[$_formVal[6]['n']]) - 1);
        $AL = $calc_data[$_formVal[2]['n']];
        $Ref = $calc_data[$_formVal[4]['n']];
        $needInputACD = $calc_data[$_formVal[9]['n']];
        $ACD = $calc_data[$_formVal[5]['n']];
        $t->resetFormulaVal(
            $calc_data[$_formVal[3]['n']],
            $Ref,
            $needInputACD,
            $calc_data[$_formVal[7]['n']],
            $calc_data[$_formVal[8]['n']],
            $ACD
        );

        $refInd = $t->_SUPERFORMULA(
            $AdjustedInputK3,
            $AL,
            $Ref
        );
        $yPst = ($refInd >= 10) ? $t->_SUPERFORMULA($K3, $AL, $Ref) : $refInd;

        $points = array();
        $faces = array();
        $xBgn = 20;
        $yBgn = 37;
        $abscissSize = 10;
        $delta = array("x" => 0.0, "y" => ($abscissSize - 1.2) / $K3, "z" => $abscissSize / $K3);
        $step = 0.5;
        $indx = 0;
        $indStep = (1 / $step) * $abscissSize;
        $maxByIt = 10 - $step;
        $_yMax = 0;
        $_yMin = 0;
        $curMaxY = 0;
        $strt = 0;

        for ($i = $strt; $i <= $abscissSize; $i += $step) {
            $curX = $xBgn + $i;
            $curY = $yBgn;
            for ($k = $strt; $k <= $abscissSize; $k += $step) {
                $curMaxY = ($t->_SUPERFORMULA($curY + $k, $curX, $Ref)) * $delta['y'];
                if ($_yMax < $curMaxY) $_yMax = $curMaxY;
                if ($_yMin > $curMaxY) $_yMin = $curMaxY;
                $points[] = array("x" => $curX, "y" => $curMaxY, "z" => $curY + $k);

                if ($k <= $maxByIt) {
                    if ($i <= $maxByIt) {
                        $faces[] = array("x" => ($indx + 1), "y" => ($indx), "z" => ($indx + 1 + ($indStep)));
                        $faces[] = array("x" => ($indx + 1), "y" => ($indx + 1 + $indStep), "z" => ($indx + 2 + ($indStep)));
                    }
                }
                $indx++;
            }
        }
        $isAcdSet = (!empty($ACD) && is_numeric($ACD) ? "yeas" : $needInputACD);
        $iolRef = $this->iolRefCreate($AdjustedInputK3, $AL, $Ref, $isAcdSet);
        if (empty($iolRef)) {
            return array("error" => "An ACD value is required for the provided axial length", "ACDneedToBeRequred" => "yes");
        } else {

            $param = array(
                "_zSt" => ceil(round($K3) / 10),
                "points" => $points,
                "faces" => $faces,
                "_yMax" => $_yMax,
                "_yMin" => $_yMin,
                "yPst" => $yPst,
                "indStep" => $indStep,
                "abscissSize" => $abscissSize,
                "step" => $step,
                "iolRef" => $iolRef,
                "poinPst" => array("x" => $K3, "y" => $yPst * $delta['y'], "z" => $AL)
            );
            return array('success' => "success", "calc" => $param);
        }
    }

    private function postPlot($data)
    {

        $valid = $this->validatenputFields($data);
        if ($valid['error']) return $valid;

        $calc_data = json_decode(json_encode($valid['calc_data']));
        $this->setPostForm(new PostCalc($calc_data));
        $arr = $this->getPostForm()->getData($calc_data->Pics);
        foreach ($arr['data'] as $k => $val) {
            if (is_nan($val)) $arr['data'][$k] = "--";
        }

        return $arr;
    }


    private function changeNan($arr)
    {


        return $arr;
    }

    /*--------------------------------statistic------------*/
    public function setStatistic($data)
    {
        $user = empty($data->user) ? json_decode(json_encode($_SESSION['user'])) : $data->user;
        if (empty($data) || empty($data->statistic) || empty($user) || empty($user->id_user)) return array("error" => "could not saved statistic " . $user);

        $statistic = $this->loadStatistic(json_decode(json_encode(array("user" => array("id_user" => $user->id_user)))));
        if (empty($statistic) || isset($statistic["error"])) {
            return array("error" => "could not saved statistic, user did not found");
        } else {
            $statistic = $statistic['statistic'];//$res->fetch_assoc();
            $needUpdate = isset($statistic['id_user']);

            $sql = $needUpdate ? "UPDATE `{$this->tables[5]}` SET " : "INSERT into `{$this->tables[5]}`";
            $upDat = "";
            $keys = "(";
            $values = '(';
            $updateFields = array('st_visites', 'st_printes', 'st_logines', 'st_calcs');
            foreach ($data->statistic as $key => $value) {
                if (!($value instanceof stdClass) && $key != 'id_user' && $key != 'id_statistic') {
                    if ($needUpdate) {
                        for ($i = 0; $i < count($updateFields); $i++) {
                            if ($key == $updateFields[$i]) {
                                $updateVal = intval($statistic[$key]) + 1;
                                $upDat .= '`' . htmlspecialchars($key) . '` = ' . ('"' . (($updateVal)) . '", ');
                                break;
                            }
                        }
                    } else {
                        $values .= '"' . (htmlspecialchars($value)) . '", ';
                        $keys .= '`' . htmlspecialchars($key) . '`, ';
                    }
                }
            }
            if ($upDat) {
                if (strlen($upDat) == 0) return array('error' => 'it seemes like you forgot to select fields to update statistic');
                $upDat = trim($upDat, ', ') . ' ';
                $idVal = htmlspecialchars($statistic['id_statistic']);
                $sql .= $upDat . ' WHERE `id_statistic` =' . $idVal;
            } else {
                $values .= '"' . ($user->id_user) . '")';
                $keys .= '`id_user` )';
                $sql .= $keys . ' VALUES ' . $values;
            }

            $res = $this->db->query($sql);
            if (!$res) {
                return array("error" => "could not " . ($upDat ? "update" : "create") . " statistic " . $sql);
            } else {
                return array("success" => "statistic was  " . ($upDat ? "updated" : "created"), "needUpdate" => $needUpdate);
            }
        }
    }

    public function loadStatistic($data)
    {
        if (empty($data) || empty($data->user) || empty($data->user->id_user)) return array("error" => "user was not selected");
        $id = htmlspecialchars($data->user->id_user);
        $sql = "SELECT * from  `{$this->tables[5]}` WHERE `id_user` ={$id}";
        $res = $this->db->query($sql);
        if (!$res) {
            return array("error" => "could not load statistic, user did not found");
        } else {
            $statistic = $res->fetch_assoc();
            return array("success" => "user statistic was loaded", "statistic" => $statistic);
        }
    }


    /*----------------mdb file-----------*/
    public function parsemdb(){
        header('Location: http://localhost/iolcalc_mdb/');
//        $user = $this->isAuth();
//        if (isset($user['error'])) return $user['error'];

        $userName = 'sa';
        $password = 'Meditec';
        $mdb_file = '/files/IolmXP.MDB';
        $dbName = $_SERVER["DOCUMENT_ROOT"] . $mdb_file;
        if (!file_exists($dbName)) {
            return array("error"=>'file mdb could not exist '.$dbName);
        }

      $db = new PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb)}; DBQ=$dbName; Uid=$userName; Pwd=$password;");

        $sql  = "SELECT * FROM Vesrsion";

        $result = $db->query($sql);
        $row = $result->fetch();
/*        $con = new COM("ADODB.Connection");



        $con->Open(
            "Provider=Microsoft.Jet.OLEDB.4.0;" .
            "Data Source=".$dbName);
        $rst = new COM("ADODB.Recordset");
        $rst->Open("SELECT * FROM Vesrsion", $con, 1, 3);  // adOpenKeyset, adLockOptimistic
        while (!$rst->EOF) {
            var_dump( $rst. "<br/>");
            $rst->MoveNext;
        }
        $rst->Close();
        $con->Close();*/




    /*    $conn = odbc_connect("Driver={Microsoft Access Driver (*.mdb)};Dbq=$dbName", $userName, $password);
        $sql = 'SELECT * FROM Version';


        $res = $conn->Execute($sql);
        while (!$res->EOF)
        {
            print $res->Fields  . "<br>";
            $res->MoveNext();
        }*/

        return array("success" => "back up was saved ", "files" => "");
    }

    /*--------------------------------backUp------------*/
    public function backUp($data)
    {
        $info = $this->isAdmin(($data->user));
        if ($info['error']) return $info;

        $source_dir = $this->getPRODIR();
        $source_dir .= 'b25seVByaXZhdGVBY2Nlc3M=/';

        $source = "http://{$_SERVER['HTTP_HOST']}/" . MAIN_DIR . $source_dir;
        $fileNameZip = date("Y-m-d-H-i-s") . '-mcalc.zip';
        $zip_file = $source_dir . $fileNameZip;
        $sq = $this->backup_tables(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_NAME, '/' . $source_dir);
        $zip = $this->zipData($this->getPRODIR(), $zip_file);


        if ($zip["error"]) return $zip;
        if ($sq["error"]) return $sq;

        return array("success" => "back up was saved ", "files" => array(array("fileName" => $fileNameZip, "source" => $source), array("fileName" => $sq['file'], "source" => $source)));
    }

    private function backup_tables($host, $user, $pass, $name, $source_dir, $tables = '*')
    {

        $db = DB_NAME;
        $mysqli = $this->db;
        $version = "0.0.1";
        $date = date('Y-m-d H:i:s');

        $final = "--   #" . $version . "#\n";
        $final .= "--   database backup\n";
        $final .= "--\n";
        $final .= "--   PHP version: " . phpversion() . "\n";
        $final .= "--   MySQL version: " . mysqli_get_server_info($mysqli) . "\n";
        $final .= "--   Date: " . date("r") . "\n";

        $result = $mysqli->query("SHOW TABLE STATUS FROM " . $db);
        while ($table = $result->fetch_array()) {
            $i = 0;
            $result2 = $mysqli->query("SHOW COLUMNS FROM $table[0]");
            $z = $result2->num_rows;
            $final .= "\n--\n-- DB Export - Table structure for table `" . $table[0] . "`\n--\n\nCREATE TABLE `" . $table[0] . "` (";
            $prikey = false;
            $insert_keys = null;
            while ($row2 = $result2->fetch_array()) {
                $i++;
                $insert_keys .= "`" . $row2['Field'] . "`";
                $final .= "`" . $row2['Field'] . "` " . $row2['Type'];
                if ($row2['Null'] != "YES") {
                    $final .= " NOT NULL";
                }
                if ($row2['Default']) $final .= " DEFAULT '" . $row2['Default'] . "'";
                if ($row2['Extra']) {
                    $final .= " " . $row2['Extra'];
                }
                if ($row2['Key'] == "PRI") {
                    $final .= ", PRIMARY KEY  (`" . $row2['Field'] . "`)";
                    $prikey = true;
                }
                if ($i < $z) {
                    $final .= ", ";
                    $insert_keys .= ", ";
                } else {
                    $final .= " ";
                }
            }
            if ($prikey) {
                if ($table[10]) $auto_inc = " AUTO_INCREMENT=" . $table[10];
                else $auto_inc = " AUTO_INCREMENT=1";
            } else $auto_inc = "";
            $charset = explode("_", $table[14]);
            $final .= ") ENGINE=" . $table[1] . " DEFAULT CHARSET=" . $charset[0] . " COLLATE=" . $table[14] . $auto_inc . ";\n\n--\n-- DB Export - Dumping data for table `" . $table[0] . "`\n--\n";

            $inhaltq = $mysqli->query("SELECT * FROM $table[0]");
            while ($inhalt = $inhaltq->fetch_array()) {
                $final .= "\nINSERT INTO `$table[0]` (";
                $final .= $insert_keys;
                $final .= ") VALUES (";
                for ($i = 0; $i < $z; $i++) {

                    $inhalt[$i] = str_replace("'", "`", $inhalt[$i]);
                    $inhalt[$i] = str_replace("\\", "\\\\", $inhalt[$i]);
                    $einschub = "'" . $inhalt[$i] . "'";
                    $final .= preg_replace('/\r\n|\r|\n/', '\r\n', $einschub);
                    if (($i + 1) < $z) $final .= ", ";

                }
                $final .= ");";
            }
            $final .= "\n";
        }
        $return = $final;
        //save file
        $dbFileName = 'db-backup-' . date("Y-m-d-H-i-s") . '-' . (md5(implode(',', $tables))) . '.sql';
        $fileName = $source_dir . $dbFileName;
        try {
            $handle = fopen($fileName, 'w+');
            fwrite($handle, $return);
            fclose($handle);
            return array("file" => $dbFileName);

        } catch (Exception $e) {
            return array("error" => "could save sql backup " . $e);
        }

    }

    private function zipData($source, $destination)
    {
        if (extension_loaded('zip')) {
            if (file_exists($source)) {
                $zip = new ZipArchive();
                if ($zip->open($destination, ZIPARCHIVE::CREATE)) {
                    $source = realpath($source);
                    if (is_dir($source)) {
                        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);

                        foreach ($files as $file) {

                            $file = realpath($file);
                            if (preg_match('/phpmailer/', $file) ||
                                preg_match('/libs/', $file) ||
                                preg_match('/img/', $file) ||
                                preg_match('/backUp/', $file) ||
                                preg_match('/b25seVByaXZhdGVBY2Nlc3M=/', $file) ||
                                preg_match('/.git/', $file) ||
                                preg_match('/test/', $file) ||
                                preg_match('/bower_components/', $file)
                            ) {

                            } else {
                                if (is_dir($file)) {
                                    $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
                                } else if (is_file($file)) {
                                    $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
                                }
                            }

                        }
                    } else if (is_file($source)) {
                        $zip->addFromString(basename($source), file_get_contents($source));
                    }
                }
                return $zip->close();
            } else {
                return array("error" => "could'nt found  files " . $source);
            }

        } else {
            return array("error" => "could'nt zip files");
        }

    }

    private function getFilesBackUp($data)
    {
        $info = $this->isAdmin(($data->user));
        if ($info['error']) return $info;

        $source_dir = $this->getPRODIR();
        $_b = 'b25seVByaXZhdGVBY2Nlc3M=/';
        $source_dir .= "/" . $_b;

        try {
            $files = scandir($source_dir);
            foreach ($files as $key => $value) {
                if ($value == "." || $value == "..") {
                    array_splice($files, $key, 1);
                } else {
                    $files[$value] = array("fileName" => $value, "source" => "http://{$_SERVER['HTTP_HOST']}/" . MAIN_DIR . $_b);
                }

            }

            return array("success" => "files was loaded", "files" => $files);
        } catch (Exception $e) {
            return array("error" => "wrong directory");
        }

    }

    public function deleteFile($data)
    {
        if (empty($data) || empty($data->user) || empty($data->file)) return array("error" => "couldn't drop file");
        $info = $this->isAdmin(($data->user));
        if ($info['error']) return $info;

        $fileName = htmlspecialchars($data->file);
        $source_dir = $this->getPRODIR() . '/b25seVByaXZhdGVBY2Nlc3M=/' . $fileName;
        if (unlink($source_dir)) {
            return array("success" => "file was dropped");
        } else {
            return array("error" => "couldn't delete file " . $fileName);
        }
    }

    public function contact($data)
    {
        if (empty($data) || empty($data->name) || empty($data->email)) return array("error" => "couldn't send message");

        $user = array(
            'frome' => $data->email,
            'fromeName' => $data->name,
            'toMsg' => DATA_EMAIL,
            'subject' => "Contact",
            'msg' => $data->msg
        );
        return $this->sendInfoOnEmail($user, 'user', 'Thank you for requesting access.');
    }


    private function isAuth()
    {
        if (isset($_SESSION['user'])) return $_SESSION['user'];

        return array('error' => "Please authorize yourself!!!");
    }

    private function isAdmin($data)
    {
        $user = $_SESSION['user'] ? $_SESSION['user'] : $data;
        if (!$user) {
            return array('error' => 'please register');
        }
        $id_ = $user->id_user ? $user->id_user : $user['id_user'];
        $sql = "Select * from `{$this->tables[1]}` WHERE id_user ={$id_}";
        $res = $this->db->query($sql);
        $rse = $res ? $res->fetch_assoc() : false;
        if (!$res || !$rse || $rse['usr_type'] != 'Admin') {
            return array('error' => 'you don\' have permissions ');
        } else {
            return array('success' => 'success');
        }
    }

    private function getMsgByCategory($category)
    {
        $sql = "Select * from `{$this->tables[2]}` where `category` = '{$category}' ";
        $res = $this->db->query($sql);
        $rse = $res->fetch_assoc();
//        $row = mysqli_fetch_array($res, MYSQL_ASSOC);
//        var_dump(htmlspecialchars_decode($rse['text']));exit;
        if ($rse) {
            return (array("text" => (($rse['text'])), "fromeName" => $rse['fromeName'], "subject" => $rse['subject'], "frome" => $rse['fromeEmail']));
        } else {
            die(array("error" => "Sorry, if you get such messege, please confirm us"));
        }
    }

    private function sendInfoOnEmail($data, $type, $returnMsg = false, $replace = array())
    {
        $mail = new PHPMailer();
        $mail->From = $data['frome'];
        $mail->FromName = $data['fromeName'];
        $mail->AddAddress($data['toMsg']); //recipient
        $mail->Subject = $data['subject'];
        $mail->isHTML(true);
//        var_dump($data['msg']);exit;
        if ($type == 'admin') {
            $mail->Body = "Name: " . $data['fromeName'] . "\r\n\r\nFrome email: " . $data['frome'] . "\r\n\r\nMessage: " . ($data['msg']);
        } else {
            $_msg = $data['msg'];
            $listConst = $this->getListOfReplace();

//            if($replace['promo_code'])$_msg = str_replace(INVITE_CODE,$replace['promo_code'],$_msg);
//            if($replace['user_name']) $_msg = str_replace(USER_NAME,$replace['user_name'],$_msg);
//            if($replace['update_reason']) $_msg = str_replace(UPDATE_USER_REASSON,$replace['update_reason'],$_msg);


            foreach ($listConst as $k => $val) {
                $repl = '';
                $keyRepl = $val['val'];
                if ($replace[$keyRepl]) {
                    $repl = $replace[$keyRepl];
                }
                $_msg = str_replace($keyRepl, $repl, $_msg);
            }

            $mail->Body = $_msg;
        }
        if (!$mail->send()) {
            return array('error' => 'Message could not be sent. Mailer Error: ' . '---*' . $data['toMsg'] . '*---' . $mail->ErrorInfo);
        } else {
            return array('success' => ($returnMsg ? $returnMsg : 'BUG'));
        }
    }

    private function generateUniqString($length = 10)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;

    }

    private function base64url_encode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64url_decode($data)
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }

    public static function utf8ize($d)
    {
        if (is_array($d)) {
            foreach ($d as $k => $v) {
                $d[$k] = Mcalc::utf8ize($v);
            }
        } else if (is_string($d)) {
            return utf8_encode($d);
        }
        return $d;
    }

}