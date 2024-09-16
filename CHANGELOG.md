# Changelog

## 2024-09-16
### Added
* Add enum filter

## 2024-01-29
### Added
* Add GeometryNormalizer to handle Geometry Type
* Update minimal Ting version to 3.8.0

## 2024-01-25
### Fixed
* Fixed FilterCompilerPass trying to add method call setFilterDescriptionGetter() to Api Platform filter services not implementing it

## 2024-01-18
* Supports fetchEager API property and forceEager on operation

## 2023-12-11
### Fixed
* Fixed pagination for queries without joins

## 2023-11-28
### Fixed
* Fixed handling of "to Many" relationships for pagination

## 2023-10-23
### Added
* Add fulltext filter
* Add a warmer to cache filters associated with each resource
### Changed
* Require and compliant with api-platform >= 3.2
