<?php

namespace RestApi\Controller\Component;

use Cake\Controller\Component;
use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Network\Exception\UnauthorizedException;
use Cake\Network\Response;
use Firebase\JWT\JWT;
use RestApi\Routing\Exception\InvalidTokenException;
use RestApi\Routing\Exception\InvalidFingerprintException;
use RestApi\Routing\Exception\InvalidTokenFormatException;
use RestApi\Routing\Exception\MissingTokenException;
use RestApi\Routing\Exception\MissingFingerprintException;
use RestApi\Routing\Exception\ExpiredTokenException;

/**
 * Access control component class.
 *
 * Handles user authentication and permission.
 */
class AccessControlComponent extends Component
{

    /**
     * beforeFilter method.
     *
     * Handles request authentication using JWT.
     *
     * @param Event $event The startup event
     * @return Response
     */
    public function beforeFilter(Event $event)
    {
        if (Configure::read('ApiRequest.jwtAuth.enabled')) {
            return $this->_performTokenValidation($event);
        }

        return true;
    }

    /**
     * Performs token validation.
     *
     * @param Event $event The startup event
     * @return bool
     * @throws UnauthorizedException If missing or invalid token
     */
    protected function _performTokenValidation(Event $event)
    {
        $request = $event->subject()->request;

        if (!empty($request->params['allowWithoutToken']) && $request->params['allowWithoutToken']) {
            return true;
        }

        $token = '';

        $header = $request->header('Authorization');

        if (!empty($header)) {
            $parts = explode(' ', $header);

            if (count($parts) < 2 || empty($parts[0]) || !preg_match('/^Bearer$/i', $parts[0])) {
                throw new InvalidTokenFormatException();
            }

            $token = $parts[1];
        } elseif (!empty($this->request->query('token'))) {
            $token = $this->request->query('token');
        } elseif (!empty($request->data['token'])) {
            $token = $request->data['token'];
        } else {
            throw new MissingTokenException();
        }

        $fingerprintHeader = $this->request->header('fingerprint');

        if (!empty($fingerprintHeader)) {
            $fingerprint = $fingerprintHeader;
        } elseif (!empty($this->request->query('fingerprint'))) {
            $fingerprint = $this->request->query('fingerprint');
        } elseif (!empty($request->data['fingerprint'])) {
            $fingerprint = $request->data['fingerprint'];
        } else {
            throw new MissingFingerprintException();
        }

        try {
            $payload = JWT::decode($token, Configure::read('ApiRequest.jwtAuth.cypherKey'), [Configure::read('ApiRequest.jwtAuth.tokenAlgorithm')]);
        } catch (\Exception $e) {
            throw new InvalidTokenException();
        }

        if (empty($payload)) {
            throw new InvalidTokenException();
        }

        if(isset($payload->expireAt)){
            if(time() > $payload->expireAt){
                throw new ExpiredTokenException();
            }
        }

        if(isset($payload->encriptedFingerprint)){
            $encriptedFingerprint = md5(Configure::read('ApiRequest.jwtAuth.cypherKey').$fingerprint); 
            if($payload->encriptedFingerprint != $encriptedFingerprint){
                throw new InvalidFingerprintException();
            }
            $controller->fingerprint = $encriptedFingerprint;
        }

        $controller = $this->_registry->getController();

        $controller->jwtPayload = $payload;

        $controller->jwtToken = $token;

        

        Configure::write('accessToken', $token);
        Configure::write('accessFingerprint', $fingerprint);

        return true;
    }
}
