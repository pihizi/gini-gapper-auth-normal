<?php

namespace Gini\Controller\CGI\AJAX\Layout;

class Header extends \Gini\Controller\CGI
{
    public function __index()
    {
        $me = _G('ME');
        if ($me->id) {
            $cart_navbar = \Gini\CGI::request('ajax/cart/navbar', $this->env)->execute()->content();
            $cart_brief = \Gini\CGI::request('ajax/cart/brief', $this->env)->execute()->content();
        }

        $vars = [
            'route' => $this->env['route'],
            'form' => $this->form(),
            'cart_navbar' => $cart_navbar,
            'cart_brief' => $cart_brief,
            // 实现扫二维码添加用户的功能
            // 最简单的实现是手机扫描二维码，拿到一个号，这个号查找库中对应的数据，自动填充
            'extend_menu' => (string)V('layout/tust-extend-menu'),
        ];

        return \Gini\IoC::construct('\Gini\CGI\Response\HTML', V('layout/header', $vars));
    }
}
