<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Serializer\Normalizer;

use Brick\Geo\Geometry;
use Brick\Geo\IO\GeoJSONReader;
use Brick\Geo\IO\GeoJSONWriter;
use Exception;
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

use function is_array;
use function is_subclass_of;
use function json_encode;

final class GeometryNormalizer implements NormalizerInterface, DenormalizerInterface
{

    /** @return array<string,bool> */
    public function getSupportedTypes(?string $format): array
    {
        return [
            Geometry::class => true,
        ];
    }

    /**
     * @param mixed $data
     * @param array{deserialization_path?: null|string} $context
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): Geometry
    {
        if (!class_exists(Geometry::class) || !class_exists(GeoJSONReader::class)) {
            throw new \RuntimeException("Package brick/geo is required to handle Geometry. Please run `composer require brick/geo`");
        }

        if (!is_array($data)) {
            throw NotNormalizableValueException::createForUnexpectedDataType(
                'The data should be a geojson string.',
                $data,
                [Type::BUILTIN_TYPE_ARRAY],
                $context['deserialization_path'] ?? null,
                true,
            );
        }

        try {
            $geometryString = json_encode($data);
            if (false === $geometryString) {
                throw new Exception("Cannot encode to json to array");
            }

            $denormalize = (new GeoJSONReader())->read($geometryString);

            if (!$denormalize instanceof Geometry) {
                throw NotNormalizableValueException::createForUnexpectedDataType(
                    'The data should be a Geometry geojson string. Feature and FeatureCollection are not supported',
                    $data,
                    [Type::BUILTIN_TYPE_ARRAY],
                    $context['deserialization_path'] ?? null,
                    true,
                );
            }
            return $denormalize;

        } catch (Exception $e) {
            throw NotNormalizableValueException::createForUnexpectedDataType(
                $e->getMessage(),
                $data,
                [Type::BUILTIN_TYPE_ARRAY],
                $context['deserialization_path'] ?? null,
                true,
                $e->getCode(),
                $e,
            );
        }
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return class_exists(Geometry::class) && ($type === Geometry::class || is_subclass_of($type, Geometry::class));
    }

    /**
     * @param Geometry $object
     * @param array{deserialization_path?: null|string} $context
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): string
    {
        if (!class_exists(Geometry::class) || !class_exists(GeoJSONWriter::class)) {
            throw new \RuntimeException("Package brick/geo is required to handle Geometry. Please run `composer require brick/geo`");
        }

        if (!$object instanceof Geometry) {
            throw NotNormalizableValueException::createForUnexpectedDataType(
                'The data should be a Geometry Object.',
                $object,
                [Type::BUILTIN_TYPE_ARRAY],
                $context['deserialization_path'] ?? null,
                true,
            );
        }
        return (new GeoJSONWriter())->write($object);
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return class_exists(Geometry::class) && $data instanceof Geometry;
    }
}