# [1.7.2] - 2023-10-10
## Updated
- Parsed getVersion to int before adding 1
# [1.7.1] - 2023-08-24
## Updated
- Fixed str_replace when empty
# [1.7.0] - 2022-01-26
## Updated
- replaced old serialize methods with magic serialize methods to be compatible with parent serialization

# [1.6.8] - 2020-11-09
## Updated
- replaced curly brackets with square brackets to be compatible with PHP7.4

# [1.6.7] - 2020-05-28
## Updated
- fixed getEntryFromValue when a related entry does not exist

# [1.6.6] - 2020-04-19
## Updated
- improved search when doing find with 'query' option

# [1.6.5] - 2020-02-28
## Updated
- fixed entry setters for strings

# [1.6.4] - 2020-01-14
## Updated
- fixed query cache for paginated queries

# [1.6.3] - 2019-11-05
## Updated
- removed usage of deprecated each function

# [1.6.2] - 2019-09-19
## Updated
- fixed boolean handling of related entry in result parser

# [1.6.1] - 2019-09-11
## Updated
- fixed dirty detection of ordered has many field

# [1.6.0] - 2019-04-24
## Added
- added escape entry formatter

# [1.5.0] - 2019-04-17
## Added
- added string implementation for the order find option

# [1.4.3] - 2019-03-08
## Updated
- improved handling of IN keyword

# [1.4.2] - 2019-03-08
## Updated
- improved handling of IN keyword

# [1.4.1] - 2019-03-05
## Updated
- catch null when checking of order find options

# [1.4.0] - 2019-03-05
## Added
- added match implementation
## Updated
- fixed checking of order find options

# [1.3.3] - 2018-11-20
## Updated
- maximum length for a slug is 250
 
# [1.3.2] - 2018-09-25
## Updated
- increased limit of entry list options to 2000 

# [1.3.1] - 2018-09-12
## Updated
- fixed indexOn for hasMany relations with a link model

# [1.3.0] - 2018-05-17
## Added
- added method to clear all proxies from all loaded models

# [1.2.5] - 2018-02-14
## Updated
- initialize fields when adding fields with variables to query

# [1.2.3, 1.2.4] - 2017-11-02
## Updated
- fixed entry state for hasMany changes, fixed entry state comparison for properties

# [1.2.2] - 2017-08-09
## Updated
- removed setOperator in ModelQuery and GenericModel for hasOne relations to get certain custom behaviours working as they should

# [1.2.1] - 2017-07-18
## Updated
- fixed convertEntryToArray and getEntryFromValue for hasMany fields

# [1.2.0] - 2017-07-05
## Added
- added code.type and code.nullable option for model fields, used by the _serialize_ field type

# [1.1.0] - 2017-04-07
## Added
- implemented distinct option for find()

# [1.0.7] - 2017-03-28
## Updated
- $owner from setOwner in OwnedEntry can be null

# [1.0.6] - 2017-02-22
## Updated
- updated proxy to really fix save of localized hasMany on insert

# [1.0.5] - 2017-02-22
## Updated
- fixed hasOne relation to self with link model

# [1.0.4] - 2017-02-22
## Updated
- updated proxy to fix save of localized hasMany on insert

# [1.0.3] - 2017-02-09
## Updated
- removed constructor argument since there is no constructor

# [1.0.2]
## Updated
- force insert of entries with only has relations and no properties or belongs to

# [1.0.1]
## Updated
- id value from 0 to null when inserting an entry with only localized models

# [1.0.0]
## Updated
- composer.json for 1.0
