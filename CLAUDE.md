# GTK Data Access Framework Guidelines

## Build & Test Commands
- `composer install` - Install dependencies
- `composer test` - Run all PHPUnit tests 
- `composer test:mysql` - Run tests with MySQL configuration
- `phpunit --filter TestName` - Run specific test
- `vendor/bin/phpunit tests/Unit/TestFile.php` - Run tests in specific file

## Code Style
- Classes: PascalCase (`DataAccess`, `ColumnMappingException`)
- Methods: camelCase (`valueForKey()`, `withTransaction()`)
- Properties: camelCase (`$tableName`, `$primaryKey`)
- Constants: UPPER_SNAKE_CASE (`DEFAULT_LIMIT`)
- Use type hints where possible (PHP 8.1+ features supported)
- DocBlocks for complex methods with @param and @return

## Architecture Patterns
- DataAccess classes extend base DataAccess class
- Define column mappings via protected setupColumnMapping() method
- Use DataAccessManager::get() to access data objects (usually of type `DataAccess`)
- Error handling via custom exceptions in src/Exceptions
- Audit trails enabled via DataAccessAuditTrait