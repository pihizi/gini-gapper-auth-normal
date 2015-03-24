<?php

namespace Gini\Controller\CLI\Gapper\Auth;

use \Overtrue\Pinyin\Pinyin;

class Tust extends \Gini\Controller\CLI
{
    use \Gini\Module\Gapper\Client\RPCTrait;

    public function __index($args)
    {
        echo "Available commands:\n";
        echo "  gini gapper auth tust active\n";
    }

    public function actionActive()
    {
        $code = $this->getData([
            'code'=> [
                'title'=> '加密字符串',
                'example'=> '',
                'default'=> ''
            ],
        ]);
        $code = $code['code'];

        $data = $this->getData([
            'department'=> [
                'title'=> '学院',
                'example'=> '',
                'default'=> ''
            ],
            'group'=> [
                'title'=> '课题组名称',
                'example'=> '',
                'default'=> ''
            ],
            'name'=> [
                'title'=> 'PI姓名',
                'example'=> '',
                'default'=> ''
            ],
            'wid'=> [
                'title'=> 'PI工号',
                'example'=> '',
                'default'=> ''
            ],
            'email'=> [
                'title'=> '电子邮箱',
                'example'=> '',
                'default'=> ''
            ],
            'phone'=> [
                'title'=> '联系电话',
                'example'=> '',
                'default'=> ''
            ],
            'address'=> [
                'title'=> '地址',
                'example'=> '',
                'default'=> ''
            ],
        ]);

        extract($data);

        $secret = \Gini\Config::get('app.tust_secret');
        if (false===strpos(hash_hmac('sha1', json_encode([
            'wid'=> $wid,
            'name'=> $name,
            'department'=> $department,
            'group'=> $group,
            'email'=> $email,
            'phone'=> $phone,
            'address'=> $address
        ]), $secret), $code)) {
            return $this->showError('数据加密无效!');
        }

        $title = $group;

        $rpc = self::getRPC();

        $group = Pinyin::pinyin($title, [
            'delimiter'=> '',
            'accent'=> false,
        ]);

        try {
            $group = $rpc->gapper->group->getRandomGroupName($group);
        } catch (\Exception $e) {
            echo $e->getMessage();
        }

        if (!$group) {
            echo '获取组唯一标识失败！';
            echo "\n";
            return;
        }

        try {
            $info = $rpc->gapper->user->getInfo($email);
            $uid = $info['id'];
            if (!$uid) {
                // 如果以email为账号的用户不存在，尝试创建
                $password = \Gini\Util::randPassword();
                $uid = $rpc->gapper->user->registerUser([
                    'username'=> $email,
                    'password'=> $password,
                    'name'=> $name,
                    'email'=> $email
                ]);
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
        }

        if (!$uid) {
            echo '注册用户失败！';
            echo "\n";
            return;
        }

        // 绑定identity
        // 绑定失败，导致email被占用，如果用户想在以这个email激活，将直接报错
        // 所以，需要联系网站客服
        $identitySource = 'tust';
        try {
            $bool = $rpc->gapper->user->linkIdentity((int)$uid, $identitySource, $wid);
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
        if (!$bool) {
            echo "linkIdentity失败！uid:{$uid}";
            return;
        }

        $config = \Gini\Config::get('gapper.rpc');

        // 如果用户所属的组已经安装了该app, 则不需要再创建组和绑定app的操作
        $needNewGroup = true;
        try {
            $gps = (array)$rpc->gapper->user->getGroups((int)$uid);
            foreach ($gps as $gpid=>$gp) {
                if (!$gpid) continue;
                if (!$gp['admin']) continue;
                $apps = (array)$rpc->gapper->group->getApps((int)$gpid);
                foreach ($apps as $appid=>$app) {
                    if ($appid===$config['client_id']) {
                        $needNewGroup = false;
                        $home = $app['url'];
                        break;
                    }
                }
                if (!$needNewGroup) break;
            }
        }
        catch (\Exception $e) {
            echo $e->getMessage();
            return;
        }

        if ($needNewGroup) {
            // 创建新分组
            try {
                $gid = $rpc->gapper->group->create([
                    'user'=> (int)$uid,
                    'name'=> $group,
                    'title'=> $title
                ]);
            } catch (\Exception $e) {
                echo $e->getMessage();
            }

            if (!$gid) {
                echo "创建group失败! uid:{$uid}";
                echo "\n";
                return;
            }

            // 绑定app
            try {
                $bool = $rpc->gapper->app->installTo($config['client_id'], 'group', (int)$gid);
            } catch (\Exception $e) {
                echo $e->getMessage();
            }
            if (!$bool) {
                echo "group绑定app失败! uid:{$uid} gid:{$gid}";
                echo "\n";
                return;
            }
        }

        $record->atime = date('Y-m-d H:i:s');
        if ($password) {
            $record->password = $password;
        }
        $record->save();

        if (!$home) {
            try {
                $app = $rpc->gapper->app->getInfo($config['client_id']);
                $home = $app['url'];
            } catch (\Exception $e) {
                echo $e->getMessage();
            }
        }

        // 创建成功，发送邮件告知用户名和密码
        $mail = \Gini\IoC::construct('\Gini\Mail');
        $mail->to($email, $name)
            ->subject(T('新用户已开通！'))
            ->body('', (string)V('gapper/auth/tust/cli-mail', [
                    'name'=> $name,
                    'email'=> $email,
                    'group'=> $title,
                    'password'=> $password,
                    'home'=> $home
                ]))
            ->send();
    }

    public function getData($data)
    {
        $result = [];
        foreach ($data as $k => $v) {
            $tmpTitle = $v['title'];
            $tmpEG = $v['example'] ? " (e.g \e[31m{$v['example']}\e[0m)" : '';
            $tmpDefault = $v['default'] ? " default value is \e[31m{$v['default']}\e[0m" : '';
            $tmpData = readline($tmpTitle . $tmpEG . $tmpDefault . ': ');
            if (isset($v['default']) && !$tmpData) {
                $tmpData = $v['default'];
            }
            if (isset($tmpData) && $tmpData!=='') {
                $result[$k] = $tmpData;
            }
        }

        return $result;
    }

    public function surround($string)
    {
        return "\e[31m" . $string . "\e[0m";
    }

    public function show($msg)
    {
        echo $msg . "\n";
    }

    public function showError($msg)
    {
        $this->show($this->surround($msg));
    }

}
