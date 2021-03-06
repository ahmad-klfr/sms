<?php

namespace OpiloClient\V2;

use DateTime;
use GuzzleHttp\Client;
use OpiloClient\Configs\Account;
use OpiloClient\Configs\ConnectionConfig;
use OpiloClient\Request\OutgoingSMS;
use OpiloClient\Response\CheckStatusResponse;
use OpiloClient\Response\CommunicationException;
use OpiloClient\Response\Credit;
use OpiloClient\Response\Inbox;
use OpiloClient\Response\SendError;
use OpiloClient\Response\SendSMSResponse;
use OpiloClient\Response\SMSId;
use OpiloClient\V2\Bin\Parser;
use OpiloClient\V2\Bin\Out;

class HttpClient
{
    /**
     * @var Account
     */
    protected $account;

    /**
     * @var Client
     */
    protected $client;

    public function __construct(ConnectionConfig $config, Account $account)
    {
        $this->client = $config->getHttpClient(ConnectionConfig::VERSION_2);
        $this->account = $account;
    }

    /**
     * @param OutgoingSMS|OutgoingSMS[] $messages
     *
     * @throws CommunicationException
     *
     * @return SendSMSResponse[]|SMSId[]|SendError[]
     */
    public function sendSMS($messages)
    {
        if (!is_array($messages)) {
            $messages = [$messages];
        }
        $request = $this->client->createRequest('POST', 'sms/send', [
            'json' => Out::attachAuth($this->account, Out::SMSArrayToSendRequestBody($messages)),
        ]);
        $response = Out::send($this->client, $request);

        return Parser::prepareSendResponse($response);
    }

    /**
     * @param int                  $minId
     * @param DateTime|string|null $minReceivedAt
     * @param string               $read
     *
     * @see Inbox::INBOX_ALL, Inbox::INBOX_READ, Inbox::INBOX_NOT_READ
     *
     * @param string|null $line_number
     *
     * @return Inbox
     *
     * @throws CommunicationException
     */
    public function checkInbox($minId = 0, $minReceivedAt = null, $read = Inbox::INBOX_ALL, $line_number = null)
    {
        $query = [];

        if ($minId) {
            $query['min_id'] = $minId;
        }

        if ($minReceivedAt) {
            if ($minReceivedAt instanceof DateTime) {
                $query['min_received_at'] = $minReceivedAt->format('Y-m-d H:i:s');
            } else {
                $query['min_received_at'] = $minReceivedAt;
            }
        }

        if ($read != Inbox::INBOX_ALL) {
            $query['read'] = $read;
        }

        if ($line_number) {
            $query['line_number'] = $line_number;
        }

        $response = Out::send($this->client, $this->client->createRequest('GET', 'inbox', [
            'query' => Out::attachAuth($this->account, $query),
        ]));

        return Parser::prepareIncomingSMS($response);
    }

    /**
     * @param int|int[] $opiloIds
     *
     * @throws CommunicationException
     *
     * @return CheckStatusResponse
     */
    public function checkStatus($opiloIds)
    {
        if (!is_array($opiloIds)) {
            $opiloIds = [$opiloIds];
        }

        $response = Out::send($this->client, $this->client->createRequest('GET', 'sms/status', [
            'query' => Out::attachAuth($this->account, ['ids' => $opiloIds]),
        ]));

        return Parser::prepareStatusArray($response);
    }

    /**
     * @throws CommunicationException
     *
     * @return Credit
     */
    public function getCredit()
    {
        $response = Out::send($this->client, $this->client->createRequest('GET', 'credit', [
            'query' => Out::attachAuth($this->account, []),
        ]));

        return Parser::prepareCredit($response);
    }
}
