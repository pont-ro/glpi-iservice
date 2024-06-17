# 1. Step
The `Iservice` directory should be copied to `squizlabs\php_codesniffer\src\Standards` directory to make the sniff work, fpr example: `D:\UniServerZ\core\composer\Home\vendor\squizlabs\php_codesniffer\src\Standards\Iservice`.

# 2. Step
The `phpcs.xml` should be updated with the following line: `<rule ref="Iservice.ImportedFiles.ChangeClassNames"/>`
