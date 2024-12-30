<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Annotation\DiscriminatorMap;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\UidNormalizer;
use Symfony\Component\Serializer\Normalizer\UnwrappingDenormalizer;
use Symfony\Component\Serializer\Serializer as SymfonySerializer;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Doctrine\Common\Annotations\AnnotationReader;



$classMetadataFactory = null;

if (class_exists(AttributeLoader::class)) {
    /*@phpstan-ignore-next-line*/
    $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
}

if (null == $classMetadataFactory && class_exists(AnnotationLoader::class) && class_exists(AnnotationReader::class)) {
    /*@phpstan-ignore-next-line*/
    $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
}



$objectNormalizer     = new ObjectNormalizer(
    $classMetadataFactory,
    new MetadataAwareNameConverter($classMetadataFactory),
    null,
    new PropertyInfoExtractor(
        [],
        [new PhpStanExtractor()],
        [],
        [],
        []
    ),
    new ClassDiscriminatorFromClassMetadata($classMetadataFactory),
);

$serializer = new SymfonySerializer(
    [
        new UnwrappingDenormalizer(),
        new BackedEnumNormalizer(),
        new JsonSerializableNormalizer(),
        new UidNormalizer(),
        new DateTimeNormalizer(),
        $objectNormalizer,
        new ArrayDenormalizer(),
    ],
    [
        new XmlEncoder(),
        new JsonEncoder(defaultContext: [JsonEncode::OPTIONS => JSON_UNESCAPED_UNICODE]),
    ]
);



enum DocumentType: string
{
    case RF_ACTUAL   = 'RF_ACTUAL';
    case RF_PREVIOUS = 'RF_PREVIOUS';
}


#[DiscriminatorMap(
    'type',
    [
        DocumentType::RF_ACTUAL->value   => RussianPassportDocument::class,
        DocumentType::RF_PREVIOUS->value => RussianPassportDocument::class,
    ]
)]
abstract class Document
{
    public function __construct(
        public DocumentType $type,
    ) {}
}


final class RussianPassportDocument extends Document
{
    public function __construct(DocumentType $type)
    {
        if (!in_array($type, [DocumentType::RF_ACTUAL, DocumentType::RF_PREVIOUS])) {
            throw new \InvalidArgumentException('Wrong document type');
        }

        parent::__construct($type);
    }
}


dd(
    $serializer->serialize([new RussianPassportDocument(DocumentType::RF_ACTUAL), new RussianPassportDocument(DocumentType::RF_PREVIOUS)], 'json'), // output: [{"type":"RF_ACTUAL"},{"type":"RF_ACTUAL"}]". WTF?! 🥴
    $serializer->deserialize('[{"type":"RF_ACTUAL"},{"type":"RF_PREVIOUS"}]', Document::class . '[]', 'json'), // output: [{"type":"RF_ACTUAL"},{"type":"RF_PREVIOUS"}]. Great work 😍
);