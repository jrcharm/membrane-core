<?php

declare(strict_types=1);

namespace OpenAPI\Builder;

use cebe\openapi\Reader;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Parameter;
use cebe\openapi\spec\PathItem;
use cebe\openapi\spec\Schema;
use GuzzleHttp\Psr7\ServerRequest;
use Membrane\Builder\Specification;
use Membrane\Filter\Type\ToInt;
use Membrane\OpenAPI\Builder\APIBuilder;
use Membrane\OpenAPI\Builder\Arrays;
use Membrane\OpenAPI\Builder\Numeric;
use Membrane\OpenAPI\Builder\OpenAPIRequestBuilder;
use Membrane\OpenAPI\Builder\RequestBuilder;
use Membrane\OpenAPI\Builder\Strings;
use Membrane\OpenAPI\Exception\CannotProcessOpenAPI;
use Membrane\OpenAPI\Exception\CannotProcessSpecification;
use Membrane\OpenAPI\ExtractPathParameters\PathMatcher as PathMatcherClass;
use Membrane\OpenAPI\ExtractPathParameters\PathParameterExtractor;
use Membrane\OpenAPI\Filter\HTTPParameters;
use Membrane\OpenAPI\Filter\PathMatcher;
use Membrane\OpenAPI\Method;
use Membrane\OpenAPI\Processor\Json;
use Membrane\OpenAPI\Processor\Request as RequestProcessor;
use Membrane\OpenAPI\Reader\OpenAPIFileReader;
use Membrane\OpenAPI\Specification\APISchema;
use Membrane\OpenAPI\Specification\OpenAPIRequest;
use Membrane\OpenAPI\Specification\Request;
use Membrane\Processor;
use Membrane\Processor\BeforeSet;
use Membrane\Processor\Collection;
use Membrane\Processor\Field;
use Membrane\Processor\FieldSet;
use Membrane\Result\FieldName;
use Membrane\Result\Message;
use Membrane\Result\MessageSet;
use Membrane\Result\Result;
use Membrane\Validator\FieldSet\RequiredFields;
use Membrane\Validator\Numeric\Maximum;
use Membrane\Validator\Type\IsFloat;
use Membrane\Validator\Type\IsInt;
use Membrane\Validator\Type\IsList;
use Membrane\Validator\Type\IsString;
use Membrane\Validator\Utility\Passes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

#[CoversClass(OpenAPIRequestBuilder::class)]
#[CoversClass(APIBuilder::class)]
#[CoversClass(CannotProcessSpecification::class)]
#[CoversClass(CannotProcessOpenAPI::class)]
#[UsesClass(RequestBuilder::class)]
#[UsesClass(OpenAPIRequestBuilder::class)]
#[UsesClass(OpenAPIRequest::class)]
#[UsesClass(Request::class)]
#[UsesClass(Arrays::class)]
#[UsesClass(Numeric::class)]
#[UsesClass(Strings::class)]
#[UsesClass(HTTPParameters::class)]
#[UsesClass(PathMatcher::class)]
#[UsesClass(PathParameterExtractor::class)]
#[UsesClass(PathMatcherClass::class)]
#[UsesClass(Json::class)]
#[UsesClass(RequestProcessor::class)]
#[UsesClass(OpenAPIFileReader::class)]
#[UsesClass(APISchema::class)]
#[UsesClass(\Membrane\OpenAPI\Specification\Arrays::class)]
#[UsesClass(\Membrane\OpenAPI\Specification\Numeric::class)]
#[UsesClass(\Membrane\OpenAPI\Specification\Strings::class)]
#[UsesClass(Request::class)]
#[UsesClass(ToInt::class)]
#[UsesClass(BeforeSet::class)]
#[UsesClass(Collection::class)]
#[UsesClass(Field::class)]
#[UsesClass(FieldSet::class)]
#[UsesClass(FieldName::class)]
#[UsesClass(Message::class)]
#[UsesClass(MessageSet::class)]
#[UsesClass(Result::class)]
#[UsesClass(RequiredFields::class)]
#[UsesClass(Maximum::class)]
#[UsesClass(IsInt::class)]
#[UsesClass(IsList::class)]
#[UsesClass(IsString::class)]
#[UsesClass(Passes::class)]
class OpenAPIRequestBuilderTest extends TestCase
{
    public const DIR = __DIR__ . '/../../fixtures/OpenAPI/';

    #[Test, TestDox('It will support the OpenAPIRequest Specification')]
    public function supportsRequestSpecification(): void
    {
        $specification = self::createStub(OpenAPIRequest::class);
        $sut = new OpenAPIRequestBuilder();

        self::assertTrue($sut->supports($specification));
    }

    #[Test, TestDox('It will not support any Specifications other than OpenAPIRequest')]
    public function doesNotSupportSpecificationsOtherThanRequest(): void
    {
        $specification = self::createStub(\Membrane\Builder\Specification::class);
        $sut = new OpenAPIRequestBuilder();

        self::assertFalse($sut->supports($specification));
    }

    #[Test, TestDox('It currently only supports application/json content')]
    public function throwsExceptionIfParameterHasContentThatIsNotJson(): void
    {
        $openApi = Reader::readFromJsonFile(__DIR__ . '/../../fixtures/OpenAPI/noReferences.json');
        $specification = new OpenAPIRequest(
            new PathParameterExtractor('/requestpathexceptions'),
            $openApi->paths->getPath('/requestpathexceptions'),
            Method::POST
        );
        $sut = new OpenAPIRequestBuilder();

        $mediaTypes = array_keys($openApi->paths->getPath('/requestpathexceptions')->post->parameters[0]->content);

        self::expectExceptionObject(CannotProcessOpenAPI::unsupportedMediaTypes($mediaTypes));

        $sut->build($specification);
    }

    public static function dataSetsForBuild(): array
    {
        $openApi = Reader::readFromJsonFile(__DIR__ . '/../../fixtures/OpenAPI/noReferences.json');

        return [
            'Request: no path params, no operation params, no requestBody' => [
                new OpenAPIRequest(
                    new PathParameterExtractor('/path'),
                    $openApi->paths->getPath('/path'),
                    Method::GET
                ),
                new RequestProcessor(
                    '',
                    'path-get',
                    Method::GET,
                    [
                        'path' => new FieldSet(
                            'path',
                            new BeforeSet(
                                new PathMatcher(new PathParameterExtractor('/path'))
                            )
                        ),
                        'query' => new FieldSet('query', new BeforeSet(new HTTPParameters())),
                        'header' => new FieldSet('header'),
                        'cookie' => new FieldSet('cookie'),
                        'body' => new Json(new Field('requestBody', new Passes())),
                    ]

                ),
            ],
            'Request: path param in path, no operation params, no requestBody' => [
                new OpenAPIRequest(
                    new PathParameterExtractor('/requestpathone/{id}'),
                    $openApi->paths->getPath('/requestpathone/{id}'),
                    Method::GET
                ),
                new RequestProcessor(
                    '',
                    'requestpathone-get',
                    Method::GET,
                    [
                        'path' => new FieldSet(
                            'path',
                            new BeforeSet(
                                new PathMatcher(new PathParameterExtractor('/requestpathone/{id}')),
                                new RequiredFields('id')
                            ),
                            new Field('id', new ToInt(), new IsInt())
                        ),
                        'query' => new FieldSet('query', new BeforeSet(new HTTPParameters())),
                        'header' => new FieldSet('header'),
                        'cookie' => new FieldSet('cookie'),
                        'body' => new Json(new Field('requestBody', new Passes())),
                    ]

                ),
            ],
            'Request: path param in path, operation param in query not required, no requestBody' => [
                new OpenAPIRequest(
                    new PathParameterExtractor('/requestpathone/{id}'),
                    $openApi->paths->getPath('/requestpathone/{id}'),
                    Method::POST
                ),
                new RequestProcessor(
                    '',
                    'requestpathone-post',
                    Method::POST,
                    [
                        'path' => new FieldSet(
                            'path',
                            new BeforeSet(
                                new PathMatcher(new PathParameterExtractor('/requestpathone/{id}')),
                                new RequiredFields('id')
                            ),
                            new Field('id', new ToInt(), new IsInt())
                        ),
                        'query' => new FieldSet(
                            'query',
                            new BeforeSet(new HTTPParameters()),
                            new Field('age', new ToInt(), new IsInt())
                        ),
                        'header' => new FieldSet('header'),
                        'cookie' => new FieldSet('cookie'),
                        'body' => new Json(new Field('requestBody', new Passes())),
                    ]
                ),
            ],
            'Request: path param in path, operation param in query required, no requestBody' => [
                new OpenAPIRequest(
                    new PathParameterExtractor('/requestpathone/{id}'),
                    new PathItem([
                        'parameters' => [
                            new Parameter([
                                'name' => 'id',
                                'in' => 'path',
                                'required' => true,
                                'schema' => new Schema(['type' => 'integer']),
                            ]),
                        ],
                        'put' => new Operation([
                            'operationId' => 'requestpathone-post',
                            'parameters' => [
                                new Parameter(
                                    [
                                        'name' => 'name',
                                        'in' => 'query',
                                        'required' => true,
                                        'schema' => new Schema(['type' => 'string']),
                                    ]
                                ),
                            ],
                        ]),
                    ]),
                    Method::PUT
                ),
                new RequestProcessor(
                    '',
                    'requestpathone-post',
                    Method::PUT,
                    [
                        'path' => new FieldSet(
                            'path',
                            new BeforeSet(
                                new PathMatcher(new PathParameterExtractor('/requestpathone/{id}')),
                                new RequiredFields('id')
                            ),
                            new Field('id', new ToInt(), new IsInt())
                        ),
                        'query' => new FieldSet(
                            'query',
                            new BeforeSet(new HTTPParameters(), new RequiredFields('name')),
                            new Field('name', new IsString())
                        ),
                        'header' => new FieldSet('header'),
                        'cookie' => new FieldSet('cookie'),
                        'body' => new Json(new Field('requestBody', new Passes())),
                    ]

                ),
            ],
            'Request: path param in path, operation param in query with json content, required, no requestBody' => [
                new OpenAPIRequest(
                    new PathParameterExtractor('/requestpathone/{id}'),
                    $openApi->paths->getPath('/requestpathone/{id}'),
                    Method::DELETE
                ),
                new RequestProcessor(
                    '',
                    'requestpathone-delete',
                    Method::DELETE,
                    [
                        'path' => new FieldSet(
                            'path',
                            new BeforeSet(
                                new PathMatcher(new PathParameterExtractor('/requestpathone/{id}')),
                                new RequiredFields('id')
                            ),
                            new Field('id', new ToInt(), new IsInt())
                        ),
                        'query' => new FieldSet(
                            'query',
                            new BeforeSet(new HTTPParameters(), new RequiredFields('name')),
                            new Field('name', new IsString())
                        ),
                        'header' => new FieldSet('header'),
                        'cookie' => new FieldSet('cookie'),
                        'body' => new Json(new Field('requestBody', new Passes())),
                    ]
                ),
            ],
            'Request: path param in header, no requestBody' => [
                new OpenAPIRequest(
                    new PathParameterExtractor('/requestpathtwo'),
                    $openApi->paths->getPath('/requestpathtwo'),
                    Method::GET
                ),
                new RequestProcessor(
                    '',
                    'requestpathtwo-get',
                    Method::GET,
                    [
                        'path' => new FieldSet(
                            'path',
                            new BeforeSet(
                                new PathMatcher(new PathParameterExtractor('/requestpathtwo'))
                            )
                        ),
                        'query' => new FieldSet('query', new BeforeSet(new HTTPParameters())),
                        'header' => new FieldSet('header', new Field('id', new ToInt(), new IsInt())),
                        'cookie' => new FieldSet('cookie'),
                        'body' => new Json(new Field('requestBody', new Passes())),
                    ]

                ),
            ],
            'Request: path param in header, operation param in cookie, no requestBody' => [
                new OpenAPIRequest(
                    new PathParameterExtractor('/requestpathtwo'),
                    $openApi->paths->getPath('/requestpathtwo'),
                    Method::POST
                ),
                new RequestProcessor(
                    '',
                    'requestpathtwo-post',
                    Method::POST,
                    [
                        'path' => new FieldSet(
                            'path',
                            new BeforeSet(
                                new PathMatcher(new PathParameterExtractor('/requestpathtwo'))
                            )
                        ),
                        'query' => new FieldSet('query', new BeforeSet(new HTTPParameters())),
                        'header' => new FieldSet('header', new Field('id', new ToInt(), new IsInt())),
                        'cookie' => new FieldSet('cookie', new Field('name', new IsString())),
                        'body' => new Json(new Field('requestBody', new Passes())),
                    ]

                ),
            ],
            'Request: identical param in header and query, no requestBody' => [
                new OpenAPIRequest(
                    new PathParameterExtractor('/requestpathtwo'),
                    $openApi->paths->getPath('/requestpathtwo'),
                    Method::PUT
                ),
                new RequestProcessor(
                    '',
                    'requestpathtwo-put',
                    Method::PUT,
                    [
                        'path' => new FieldSet(
                            'path',
                            new BeforeSet(
                                new PathMatcher(new PathParameterExtractor('/requestpathtwo'))
                            )
                        ),
                        'query' => new FieldSet(
                            'query',
                            new BeforeSet(new HTTPParameters()),
                            new Field('id', new ToInt(), new IsInt())
                        ),
                        'header' => new FieldSet('header'),
                        'cookie' => new FieldSet('cookie'),
                        'body' => new Json(new Field('requestBody', new Passes())),
                    ]

                ),
            ],
            'Request: same param in path and operation with different types, no requestBody' => [
                new OpenAPIRequest(
                    new PathParameterExtractor('/requestpathtwo'),
                    $openApi->paths->getPath('/requestpathtwo'),
                    Method::DELETE
                ),
                new RequestProcessor(
                    '',
                    'requestpathtwo-delete',
                    Method::DELETE,
                    [
                        'path' => new FieldSet(
                            'path',
                            new BeforeSet(
                                new PathMatcher(new PathParameterExtractor('/requestpathtwo'))
                            )
                        ),
                        'query' => new FieldSet('query', new BeforeSet(new HTTPParameters())),
                        'header' => new FieldSet('header', new Field('id', new IsString())),
                        'cookie' => new FieldSet('cookie'),
                        'body' => new Json(new Field('requestBody', new Passes())),
                    ]

                ),
            ],
            'Request: requestBody param' => [
                new OpenAPIRequest(
                    new PathParameterExtractor('/requestbodypath'),
                    $openApi->paths->getPath('/requestbodypath'),
                    Method::GET
                ),
                new RequestProcessor(
                    '',
                    'requestbodypath-get',
                    Method::GET,
                    [
                        'path' => new FieldSet(
                            'path',
                            new BeforeSet(
                                new PathMatcher(new PathParameterExtractor('/requestbodypath'))
                            )
                        ),
                        'query' => new FieldSet('query', new BeforeSet(new HTTPParameters())),
                        'header' => new FieldSet('header'),
                        'cookie' => new FieldSet('cookie'),
                        'body' => new Json(new Field('requestBody', new IsInt())),
                    ]

                ),
            ],
            'Request: operation param in query, requestBody param' => [
                new OpenAPIRequest(
                    new PathParameterExtractor('/requestbodypath'),
                    $openApi->paths->getPath('/requestbodypath'),
                    Method::POST
                ),
                new RequestProcessor(
                    '',
                    'requestbodypath-post',
                    Method::POST,
                    [
                        'path' => new FieldSet(
                            'path',
                            new BeforeSet(
                                new PathMatcher(new PathParameterExtractor('/requestbodypath'))
                            )
                        ),
                        'query' => new FieldSet(
                            'query',
                            new BeforeSet(new HTTPParameters()),
                            new Field('id', new IsString())
                        ),
                        'header' => new FieldSet('header'),
                        'cookie' => new FieldSet('cookie'),
                        'body' => new Json(new Field('requestBody', new IsInt())),
                    ]

                ),
            ],
            'Request: path param in path, operation param in query, header, cookie, requestBody param' => [
                new OpenAPIRequest(
                    new PathParameterExtractor('/requestbodypath/{id}'),
                    $openApi->paths->getPath('/requestbodypath/{id}'),
                    Method::GET
                ),
                new RequestProcessor(
                    '',
                    'requestbodypath-id-get',
                    Method::GET,
                    [
                        'path' => new FieldSet(
                            'path',
                            new BeforeSet(
                                new PathMatcher(new PathParameterExtractor('/requestbodypath/{id}')),
                                new RequiredFields('id')
                            ),
                            new Field('id', new ToInt(), new IsInt())
                        ),
                        'query' => new FieldSet(
                            'query',
                            new BeforeSet(new HTTPParameters()),
                            new Field('name', new IsString())
                        ),
                        'header' => new FieldSet('header', new Field('species', new IsString())),
                        'cookie' => new FieldSet('cookie', new Field('subspecies', new IsString())),
                        'body' => new Json(new Field('requestBody', new IsFloat())),
                    ]
                ),
            ],
        ];
    }

    #[Test, TestDox('Builds a Processor for the Operation Object (specified by the PathItem and method provided')]
    #[DataProvider('dataSetsForBuild')]
    public function buildTest(Specification $spec, Processor $expected): void
    {
        $sut = new OpenAPIRequestBuilder();

        $actual = $sut->build($spec);

        self::assertEquals($expected, $actual);
    }


    public static function dataSetsForDocExamples(): array
    {
        $petstoreApi = Reader::readFromYamlFile(self::DIR . '/docs/petstore.yaml');
        $petstoreExpandedApi = Reader::readFromJsonFile(self::DIR . '/docs/petstore-expanded.json');

        return [
            'petstore /pets get, minimal (valid)' => [
                new OpenAPIRequest(
                    new PathParameterExtractor('/pets'),
                    $petstoreApi->paths->getPath('/pets'),
                    Method::GET
                ),
                new ServerRequest('get', 'http://petstore.swagger.io/v1/pets'),
                Result::valid(
                    [
                        'request' => ['method' => 'get', 'operationId' => 'listPets'],
                        'path' => [],
                        'query' => [],
                        'header' => [],
                        'cookie' => [],
                        'body' => '',
                    ],
                ),
            ],
            'petstore /pets/{petid} get, minimal (valid)' => [
                new OpenAPIRequest(
                    new PathParameterExtractor('/pets/{petId}'),
                    $petstoreApi->paths->getPath('/pets/{petId}'),
                    Method::GET
                ),
                new ServerRequest('get', 'http://petstore.swagger.io/v1/pets/Harley'),
                Result::valid(
                    [
                        'request' => ['method' => 'get', 'operationId' => 'showPetById'],
                        'path' => ['petId' => 'Harley'],
                        'query' => [],
                        'header' => [],
                        'cookie' => [],
                        'body' => '',
                    ],
                ),
            ],
            'petstore expanded /pets get (invalid)' => [
                new OpenAPIRequest(
                    new PathParameterExtractor('/pets'),
                    $petstoreExpandedApi->paths->getPath('/pets'),
                    Method::GET
                ),
                new ServerRequest('get', 'http://petstore.swagger.io/api/pets?limit=five'),
                Result::invalid(
                    [
                        'request' => ['method' => 'get', 'operationId' => 'findPets'],
                        'path' => [],
                        'query' => ['limit' => 'five'],
                        'header' => [],
                        'cookie' => [],
                        'body' => '',
                    ],
                    new MessageSet(
                        new FieldName('limit', '', 'query'),
                        new Message('ToInt filter only accepts numeric strings', [])
                    )
                ),
            ],
            'petstore expanded /pets get, minimal (valid)' => [
                new OpenAPIRequest(
                    new PathParameterExtractor('/pets'),
                    $petstoreExpandedApi->paths->getPath('/pets'),
                    Method::GET
                ),
                new ServerRequest('get', 'http://petstore.swagger.io/api/pets?limit=5&tags[]=cat&tags[]=tabby'),
                Result::valid(
                    [
                        'request' => ['method' => 'get', 'operationId' => 'findPets'],
                        'path' => [],
                        'query' => ['limit' => 5, 'tags' => ['cat', 'tabby']],
                        'header' => [],
                        'cookie' => [],
                        'body' => '',
                    ]
                ),
            ],
        ];
    }

    #[DataProvider('dataSetsForDocExamples')]
    #[Test]
    public function docsTest(
        OpenAPIRequest $specification,
        array | ServerRequestInterface $serverRequest,
        Result $expected
    ): void {
        $sut = new OpenAPIRequestBuilder();

        $processor = $sut->build($specification);

        $actual = $processor->process(new FieldName(''), $serverRequest);

        self::assertEquals($expected, $actual);
        self::assertSame($expected->value, $actual->value);
    }
}