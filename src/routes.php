<?php

use Io\Token\Proto\Common\Alias\Alias;
use Tokenio\Security\UnsecuredFileSystemKeyStore;
use Tokenio\Util\Strings;
use Tokenio\TokenClientBuilder;
use Tokenio\TokenCluster;
use Tokenio\TokenEnvironment;
use \Io\Token\Proto\Common\Token\TokenRequestPayload\AccessBody\ResourceType;
use Tokenio\Util\Util;

class TokenSample
{
    /**
     * @var UnsecuredFileSystemKeyStore
     */
    private $keyStore;

    private $keyStoreDirectory;
    private $tokenClient;
    private $member;

    public function __construct()
    {
        $this->keyStoreDirectory = __DIR__ . '/../keys/';
        $this->keyStore = new UnsecuredFileSystemKeyStore($this->keyStoreDirectory);

        $this->tokenClient = $this->initializeSDK();
        $this->member = $this->initializeMember();
    }

    private function initializeSDK()
    {
        $builder = new TokenClientBuilder();
        $builder->connectTo(TokenCluster::get(TokenEnvironment::SANDBOX));
        $builder->withKeyStore($this->keyStore);
        return $builder->build();
    }

    private function initializeMember()
    {
        $memberId = $this->getFirstMemberId();
        if (!empty($memberId)) {
            return $this->loadMember($memberId);
        } else {
            return $this->createMember();
        }
    }

    /**
     * Finds the first member id in keystore
     *
     * @return string|null
     */
    private function getFirstMemberId()
    {
        $directory = $this->keyStoreDirectory;
        if (!file_exists($directory) || !is_dir($directory)) {
            return null;
        }
        $files = array_diff(scandir($directory), array('.', '..'));
        foreach ($files as $file) {
            $filePath = $directory . DIRECTORY_SEPARATOR . $file;
            if (is_file($filePath)) {
                return str_replace('_', ':', $file);
            }
        }
        return null;
    }

    private function loadMember($memberId)
    {
        return $this->tokenClient->getMember($memberId);
    }

    private function createMember()
    {
        $email = 'asphp-' . Strings::generateNonce() . '+noverify@example.com';

        $alias = new Alias();
        $alias->setType(Alias\Type::EMAIL);
        $alias->setValue($email);

        $member = $this->tokenClient->createBusinessMember($alias);
        $member->setProfile((new \Io\Token\Proto\Common\Member\Profile())->setDisplayNameFirst("PFM Demo"));
        return $member;
    }

    /**
     * @return \Tokenio\Member
     */
    public function getMember()
    {
        return $this->member;
    }

    public function generateTokenRequestUrl($csrfToken)
    {
        $alias = $this->member->getFirstAlias();

        $tokenRequest = \Tokenio\TokenRequest::accessTokenRequestBuilder([ResourceType::ACCOUNTS, ResourceType::BALANCES])
            ->setToMemberId($this->member->getMemberId())
            ->setToAlias($alias)
            ->setRefId(Strings::generateNonce())
            ->setRedirectUrl("http://localhost:3000/fetch-balances")
            ->setCsrfToken($csrfToken)
            ->build();

        $requestId = $this->member->storeTokenRequest($tokenRequest);
        $url =  $this->tokenClient->generateTokenRequestUrl($requestId);

        return $url;
    }

    public function getTokenRequestCallback($callbackUrl, $csrfToken){
        return $this->tokenClient->parseTokenRequestCallbackUrl($callbackUrl, $csrfToken);
    }
}

$app->get('/', function ($request, $response, array $args) {
    $this->logger->info("Index.");
    return $this->renderer->render($response, 'index.phtml', $args);
});

$app->post('/request-balances', function ($request, $response, array $args) {
    $this->logger->info("Request balances.");
    $csrf = Strings::generateNonce();
    setcookie("csrf_token", $csrf);
    $tokenIo = new TokenSample();
    return $tokenIo->generateTokenRequestUrl($csrf);
});

$app->get('/fetch-balances', function ($request, $response, array $args) {
    $this->logger->info("Fetch balances.");

    $tokenSample = new TokenSample();
    $callback = $tokenSample->getTokenRequestCallback($request->getUri(), $request->getCookieParams()["csrf_token"]);

    $member = $tokenSample->getMember();

    $representable = $member->forAccessToken($callback->getTokenId(), false);
    $accounts = $representable->getAccounts();

    $balances = array();

    foreach ($accounts as $account) {
        $balance = $account->getBalance(\Io\Token\Proto\Common\Security\Key\Level::STANDARD)->getCurrent();
        $balances[] = Util::toJson($balance);
    }

    $data = array('balances' => $balances);
    return $response->withJson($data);
});
