<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-14
 * Time: 21:07
 */

namespace Oasis\Mlib\Http\Test\Helpers\Security;

use Symfony\Component\Security\Core\User\UserInterface;

class TestApiUser implements UserInterface, \JsonSerializable
{
    protected $username;
    protected $roles;
    
    public function __construct($username, $roles)
    {
        $this->roles    = $roles;
        $this->username = $username;
    }
    
    /**
     * Returns the roles granted to the user.
     *
     * @return array The user roles
     */
    public function getRoles(): array
    {
        return $this->roles;
    }
    
    /**
     * Returns the identifier for this user (e.g. username or email address).
     *
     * @return string The user identifier
     */
    public function getUserIdentifier(): string
    {
        return $this->username;
    }
    
    /**
     * Returns the username used to authenticate the user.
     *
     * @return string The username
     * @deprecated Use getUserIdentifier() instead
     */
    public function getUsername()
    {
        return $this->username;
    }
    
    /**
     * Removes sensitive data from the user.
     */
    public function eraseCredentials(): void
    {
    }
    
    /**
     * Specify data which should be serialized to JSON
     *
     * @return mixed data which can be serialized by json_encode
     */
    public function jsonSerialize(): mixed
    {
        return $this->getUsername();
    }
}
