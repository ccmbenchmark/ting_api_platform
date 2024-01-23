<?php

declare(strict_types=1);

namespace CCMBenchmark\Ting\ApiPlatform\Serializer\Normalizer;

use Brick\Geo\Geometry;
use Brick\Geo\IO\GeoJSONReader;
use Brick\Geo\IO\GeoJSONWriter;
use Exception;
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

use function is_array;
use function is_subclass_of;
use function json_encode;

/**
 *
 * @method array getSupportedTypes(?string $format)
 */
final class GeometryNormalizer implements DenormalizerInterface, CacheableSupportsMethodInterface, NormalizerInterface
{
    /**
     * @param  $data
     * @param array{deserialization_path?: null|string} $context
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): Geometry
    {
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
            if (false === $geometryString){
                throw new Exception("Cannot encode to json to array");
            }

            return (new GeoJSONReader())->read($geometryString);

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

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null): bool
    {
        return $type === Geometry::class || is_subclass_of($type, Geometry::class);
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return true;
    }

    /**
     * @param Geometry $object
     * @param array{deserialization_path?: null|string} $context
     */
    public function normalize(mixed $object, ?string $format = null, array $context = [])
    {
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

    public function supportsNormalization(mixed $data, ?string $format = null): bool
    {
        return $data instanceof Geometry;
    }
}