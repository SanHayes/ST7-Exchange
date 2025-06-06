<?php

/*
 * This file is part of the overtrue/easy-sms.
 *
 * (c) overtrue <i@overtrue.me>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Gateways;

use Overtrue\EasySms\Contracts\MessageInterface;
use Overtrue\EasySms\Contracts\PhoneNumberInterface;
use Overtrue\EasySms\Exceptions\GatewayErrorException;
use Overtrue\EasySms\Support\Config;
use Overtrue\EasySms\Traits\HasHttpRequest;
use Overtrue\EasySms\Gateways\Gateway;

/**
 * Class QxtGateWay.
 *      http://www.bamikeji.com/
 * @see http://60.205.206.26:8888/
 */
class QxtGateway extends Gateway
{
    use HasHttpRequest;

    const ENDPOINT_URL = 'http://60.205.206.26:8888/sms.aspx';

    const ENDPOINT_METHOD = 'send';

    /**
     * @param PhoneNumberInterface $to
     * @param MessageInterface $message
     * @param Config $config
     *
     * @return \Psr\Http\Message\ResponseInterface|array|string
     *
     * @throws GatewayErrorException
     */
    public function send(PhoneNumberInterface $to, MessageInterface $message, Config $config)
    {
        $params = [
            'action' => self::ENDPOINT_METHOD,
            'userid' => $config->get('userid'),
            'account' => $config->get('account'),
            'password' => $config->get('password'),
            'mobile' => $to->getNumber(),
            'content' => $message->getContent(),
        ];

        $result = $this->post(self::ENDPOINT_URL, $params);

        if ('Success' != $result['returnstatus']) {
            throw new GatewayErrorException($result['message'], $result['remainpoint'], $result);
        }

        return $result;
    }
}
