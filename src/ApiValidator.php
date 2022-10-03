<?php

namespace Codeception\Module;

/*
 * This file is part of the Codeception ApiValidator Module project
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read
 * LICENSE file that was distributed with this source code.
 *
 */

use Codeception\Lib\InnerBrowser;
use Codeception\Lib\Interfaces\DependsOnModule;
use Codeception\Module;
use Exception;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use League\OpenAPIValidation\PSR7\Exception\ValidationFailed;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class ApiValidator
 * @package Codeception\Module
 */
class ApiValidator extends Module implements DependsOnModule
{

    protected $config = [
        'schema' => ''
    ];

    protected $dependencyMessage = <<<EOF
Example configuring REST as backend for ApiValidator module.
--
modules:
    enabled:
        - ApiValidator:
            depends: [REST, PhpBrowser]
            schema: '../../web/api/documentation/swagger.yaml'
--
EOF;

    /**
     * The configured browser module that the REST module works with.
     *
     * @var InnerBrowser
     */
    protected $innerBrowser;

    /**
     * Customized REST module.
     *
     * @var REST
     */
    public $rest;

    /**
     * The error message when the validation fails.
     *
     * @var string
     */
    protected $errorMessage = '';

    /**
     * Path to the OpenAPI / Swagger schema specification file.
     *
     * @var string
     */
    protected $swaggerSchemaFile;

    /**
     * Specifies class or module which is required for current one.
     *
     * THis method should return array with key as class name and value as error message
     * [className => errorMessage]
     *
     * @return array
     */
    public function _depends(): array
    {
        return [
            REST::class       => $this->dependencyMessage,
            PhpBrowser::class => $this->dependencyMessage,
        ];
    }

    /**
     * @param REST         $rest
     * @param InnerBrowser $innerBrowser
     * @throws Exception
     */
    public function _inject(REST $rest, InnerBrowser $innerBrowser)
    {
        $this->rest = $rest;
        $this->innerBrowser = $innerBrowser;

        if ($this->config['schema']) {
            $schema = 'file://' . ($this->swaggerSchemaFile = codecept_root_dir($this->config['schema']));
            if (!file_exists($schema)) {
                throw new Exception("{$schema} not found!");
            }
        }
    }

    /**
     * Returns the current BrowserKit Request instance.
     *
     * @return \Symfony\Component\BrowserKit\Request
     */
    public function getRequest(): \Symfony\Component\BrowserKit\Request
    {
        return $this->rest->client->getInternalRequest();
    }

    /**
     * Returns the request in PSR-7 format.
     *
     * @return \Psr\Http\Message\RequestInterface
     */
    public function getPsr7Request(): RequestInterface
    {
        $internalRequest = $this->getRequest();
        $headers         = $this->innerBrowser->headers;

        return new Request(
            $internalRequest->getMethod(),
            $internalRequest->getUri(),
            $headers,
            $internalRequest->getContent()
        );
    }

    /**
     * Returns the current BrowserKit Response instance.
     *
     * @return \Symfony\Component\BrowserKit\Response
     */
    public function getResponse(): \Symfony\Component\BrowserKit\Response
    {
        return $this->rest->client->getInternalResponse();
    }

    /**
     * Returns the response in PSR-7 format.
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function getPsr7Response(): ResponseInterface
    {
        $internalResponse = $this->getResponse();

        return new Response(
            $internalResponse->getStatusCode(),
            $internalResponse->getHeaders(),
            $internalResponse->getContent()
        );
    }

    /**
     * Return the validator builder based on spec schema file extension (yaml or json).
     *
     * @return \League\OpenAPIValidation\PSR7\ValidatorBuilder
     */
    protected function getValidatorBuilder(): ValidatorBuilder
    {
        if (in_array(pathinfo($this->swaggerSchemaFile, PATHINFO_EXTENSION), ['yaml', 'yml'])) {
            return (new ValidatorBuilder())->fromYamlFile($this->swaggerSchemaFile);
        }

        return (new ValidatorBuilder())->fromJsonFile($this->swaggerSchemaFile);
    }

    /**
     * Return the request validator object described in the OpenAPI/Swagger schema definition file.
     *
     * @return \League\OpenAPIValidation\PSR7\RequestValidator
     */
    public function getRequestValidator(): \League\OpenAPIValidation\PSR7\RequestValidator
    {
        return $this->getValidatorBuilder()->getRequestValidator();
    }

    /**
     * Return the response validator object described in the OpenAPI/Swagger schema definition file.
     *
     * @return \League\OpenAPIValidation\PSR7\ResponseValidator
     */
    public function getResponseValidator(): \League\OpenAPIValidation\PSR7\ResponseValidator
    {
        return $this->getValidatorBuilder()->getResponseValidator();
    }

    /**
     * Validate request.
     *
     * @return bool
     */
    protected function validateRequest(): bool
    {
        $validator = $this->getRequestValidator();
        $request   = $this->getPSR7Request();

        try {
            $validator->validate($request);
        } catch (ValidationFailed $e) {
            $this->errorMessage = $e->getPrevious() ? $e->getPrevious()->getMessage() : $e->getMessage();
            return false;
        }

        return true;
    }

    /**
     * Method to check the validity of the request based on the OpenAPI / Swagger schema definition file.
     */
    public function seeRequestIsValid()
    {
        $this->assertTrue($this->validateRequest(), $this->errorMessage);
    }

    /**
     * Validate response.
     *
     * @return bool
     */
    protected function validateResponse(): bool
    {
        $validator = $this->getResponseValidator();
        $request   = $this->getPSR7Request();
        $response  = $this->getPSR7Response();
        $operation = new OperationAddress($this->getPathPattern($request), strtolower($request->getMethod()));

        try {
            $validator->validate($operation, $response);
        } catch (ValidationFailed $e) {
            $this->errorMessage = $e->getPrevious() ? $e->getPrevious()->getMessage() : $e->getMessage();
            return false;
        }

        return true;
    }

    /**
     * Method to check validity of the response based on the OpenAPI / Swagger schema definition file.
     */
    public function seeResponseIsValid()
    {
        $this->assertTrue($this->validateResponse(), $this->errorMessage);
    }

    /**
     * Method to check validity of the request and the response based on the OpenAPI / Swagger schema definition file.
     */
    public function seeRequestAndResponseAreValid()
    {
        $this->seeRequestIsValid();
        $this->seeResponseIsValid();
    }

    /**
     * Determine the path pattern (replace id or uuid by generics).
     *
     * @param RequestInterface $request
     * @return string
     */
    protected function getPathPattern(RequestInterface $request): string
    {
        return preg_replace('/\/[0-9]+/', '/{id}', $request->getUri()->getPath());
    }
}
