<?php

use Io\Token\Proto\Common\Alias\Alias;
use Tokenio\Config\TokenCluster;
use Tokenio\Config\TokenEnvironment;
use Tokenio\Config\TokenIoBuilder;
use Io\Token\Proto\Common\Token\TokenRequest;
use Tokenio\Http\Request\TokenRequestOptions;
use Tokenio\Security\UnsecuredFileSystemKeyStore;
use Tokenio\Util\Strings;

class TokenSample
{
    const DEVELOPER_KEY = '4qY7lqQw8NOl9gng0ZHgT4xdiDqxqoGVutuZwrUYQsI';

    /**
     * @var UnsecuredFileSystemKeyStore
     */
    private $keyStore;

    private $keyStoreDirectory;
    private $tokenIO;
    private $member;

    public function __construct()
    {
        $this->keyStoreDirectory = __DIR__ . '/../keys/';
        $this->keyStore = new UnsecuredFileSystemKeyStore($this->keyStoreDirectory);

        $this->tokenIO = $this->initializeSDK();
        $this->member = $this->initializeMember();
    }

    private function initializeSDK()
    {
        $builder = new TokenIoBuilder();
        $builder->connectTo(TokenCluster::get(TokenEnvironment::SANDBOX));
        $builder->developerKey(self::DEVELOPER_KEY);
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
        return $this->tokenIO->getMember($memberId);
    }

    private function createMember()
    {
        $email = 'asphp-' . Strings::generateNonce() . '+noverify@example.com';

        $alias = new Alias();
        $alias->setType(Alias\Type::EMAIL);
        $alias->setValue($email);

        return $this->tokenIO->createBusinessMember($alias);
    }

    /**
     * @return \Tokenio\Member
     */
    public function getMember()
    {
        return $this->member;
    }

    public function generateTokenRequestUrl()
    {
        $alias = $this->member->getFirstAlias();
        $tokenBuilder = \Tokenio\Http\Request\AccessTokenBuilder::createWithAlias($alias)->forAll();

        $request = new TokenRequest();
        $request->setPayload($tokenBuilder->build())
                ->setOptions([TokenRequestOptions::REDIRECT_URL => 'http://localhost:3000/fetch-balances']);

        $requestId = $this->member->storeTokenRequest($request);

        return $this->tokenIO->generateTokenRequestUrl($requestId);
    }
}

$app->get('/', function ($request, $response, array $args) {
    $this->logger->info("Index.");
    return $this->renderer->render($response, 'index.phtml', $args);
});

$app->post('/request-balances', function ($request, $response, array $args) {
    $this->logger->info("Request balances.");

    $tokenIo = new TokenSample();
    return $response->withRedirect($tokenIo->generateTokenRequestUrl(), 302);
});

$app->get('/fetch-balances', function ($request, $response, array $args) {
    $this->logger->info("Fetch balances.");

    $tokenId = $request->getQueryParam('tokenId');
    if (empty($tokenId)) {
        return 'No token id found.';
    }

    $tokenIo = new TokenSample();
    $member = $tokenIo->getMember();

    $member = $member->forAccessToken($tokenId);
    $token = $member->getToken($tokenId);

    $resources = $token->getPayload()->getAccess()->getResources();

    $accounts = array();
    /** @var \Io\Token\Proto\Common\Token\AccessBody\Resource $resource */
    foreach ($resources as $resource) {
        if ($resource->getAccount() == null) {
            continue;
        }

        if (!empty($resource->getAccount()->getAccountId())) {
            $accounts[] = $resource->getAccount()->getAccountId();
        }
    }

    $balances = array();
    foreach ($accounts as $accountId) {
        $account = $member->getAccount($accountId);
        $current = $account->getCurrentBalance(\Io\Token\Proto\Common\Security\Key\Level::STANDARD);
        $balances[] = sprintf('%s %s', $current->getValue(), $current->getCurrency());
    }

    $data = array('balances' => $balances);
    return $response->withJson($data);
});
