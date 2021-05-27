<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use function source\getAbsolutePath;

/**
 * Class RIOMain
 * User can be logged in or out state
 * This area RIOMain should be used for editing user by it's user session
 * Like user start and stop time record or login and logout user
 */
class RIOMain extends RIOAccessController
{

    public function __construct(
        string $directoryNamespace,
        Environment $twig,
        Request $request
    ) {
        parent::__construct($directoryNamespace, $twig, $request);
    }

    /**
     * show home or do auto login if already an user is logged in
     *
     * @return Response
     */
    public function showHomepage(): Response
    {
       return RIORedirect::redirectResponse(["login", "unchanged"]);
    }

    public function login(string $state): Response
    {
        if(null !== $state) {
            $stateArray = ['state' => $state];
        } else {
            $stateArray = [];
        }
        return $this->renderPage(
            "home.twig",
            array_merge(
                [
                    'action' => getAbsolutePath(["postlogin"])
                ],
                $stateArray
            )
        );
    }

    /**
     * Tries to login user by current session if saved
     *
     * @return Response
     */
    public function sessionLogin(): Response
    {
        $customTwigExtension = new RIOCustomTwigExtension($this->getRequest());
        if($customTwigExtension->isLoggedIn()) {
            return RIORedirect::redirectResponse(["rioadmin", "sessionLogin"]);
        }
        return RIORedirect::redirectResponse(["login", "unchanged"]);
    }

    /**
     * Check if given user and password exists in LDAP
     *  create new MongoDB user if not exists or just insert new session id
     *
     * @param string $username
     * @param string $password
     * @return Response|null
     * @throws \Exception
     */
    private function userValidate(string $username, string $password): ?Response
    {
        /** @var resource $ldap */
        $ldap = ldap_connect($_ENV["LDAP_HOST"], $_ENV["LDAP_PORT"]);
        ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
        // TODO: uncatchable connection error on follow line below
        $search = ldap_search($ldap, $_ENV["LDAP_SEARCH_ROOT"], '(' . $_ENV["LDAP_RDN"] . '=' . $username . ')');
        $results = ldap_get_entries($ldap, $search);
        $dn = $results[0]['dn'];
        $displayUsername = $results[0]['uid'][0];
        $sessionUsername = $results[0]['uid'][1];
        $surnameUsername = $results[0]["sn"][0];
        $session = $this->getSession();
        $sessionId = $session->getId();
        $maybeObject = [
            'sessionUsername' => $sessionUsername,
            'displayUsername' => $displayUsername,
            'surnameUsername' => $surnameUsername
        ];
        $maybeAuthObject = array_merge(
          $maybeObject,
          ['sessionId' => $sessionId]
        );
        $user = new RIOUserObject();
        $authObjectNoTime = array_merge(
            $maybeAuthObject,
            [
                // New created user has by default no time record
                'timeRecordStarted' => false,
                "mandatoryTime" => $user->getMandatoryTime()->format("H:i"),
                "location" => $user->getLocation()
            ]
        );
        $auth_find = $this->getUsers()->findOne(
            $maybeObject
        );
        try {
            $bind = ldap_bind($ldap, $dn, $password);
        } catch (Exception $e) {
            if(RIOConfig::isInDebugMode()) {
                throw new Exception($e->getMessage(). ", dn = ".$dn.", password = ".$password);
            } else {
                // Username was correct but password was wrong
                return RIORedirect::redirectResponse(["login", "failure"]);
            }
        }
        if ($bind) {
            ldap_unbind($ldap);
            if("0" === (string)$this->getSession()->getMetadataBag()->getLifetime()) {
                $this->getSession()->getMetadataBag()->stampNew($_ENV["SESSION_LIFE_TIME"]);
            }
            if(null === $auth_find) {
                $this->getUsers()->insertOne(
                    $authObjectNoTime
                );
            } else {
                $this->getUsers()->updateOne(
                    $maybeObject,
                    [
                        // Update new sessionId from client
                        '$set' => [ 'sessionId' => $sessionId ]
                    ]
                );
            }
            return RIORedirect::redirectResponse(["rioadmin", "sessionLogin"]);
        } else {
            // Username or password was wrong
            return RIORedirect::redirectResponse(["login", "failure"]);
        }
    }

    /**
     * Tries to login user by post, usually called by a form
     *
     * @return Response
     * @throws Exception
     */
    public function postLogin(): Response
    {
        $request = $this->getRequest();
        $usernamePost = $request->get("username");
        $passwordPost = $request->get("password");
        if (null !== $usernamePost && null !== $passwordPost) {
            $request->getSession()->set("username", $usernamePost);
            return $this->userValidate($usernamePost, $passwordPost);
        }
        return RIORedirect::error(500);
    }
}