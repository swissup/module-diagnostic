# Swissup Diagnostic Module for Magento 2

The Swissup Diagnostic module is a tool designed for Magento 2 store owners and developers to gather essential information about their Magento environment. This command-line utility provides an overview of the PHP, Magento, and server-related details.

## Key Features:

- **Environment Information:** Details such as PHP version, Magento version, Composer version, and server user information.
  
- **Folder Structure Check:** Verify if any default Magento and Swissup modules/themes were not overwritten.

- **Magento 2 Theme Data:** Display a table of Magento 2 themes to check if no **virtual** themes exist.

- **Fixing the virtual themes**

- **Assets Optimization Manager:** Enable/disable JS and CSS merge and minification settings.

## Usage:

Execute the following commands to use the diagnostic tools:

```bash
# Display environment and system information
bin/magento swissup:info

# Fix virtual themes
bin/magento swissup:info:virtualfix

# Manage JS/CSS optimization settings
bin/magento swissup:info:assets

# Enable all optimizations (merge and minify JS/CSS/HTML)
bin/magento swissup:info:assets --enable-all

# Disable all optimizations
bin/magento swissup:info:assets --disable-all

# Enable specific settings
bin/magento swissup:info:assets --merge-css=1 --minify-css=1
bin/magento swissup:info:assets --merge-js=1 --minify-js=1
bin/magento swissup:info:assets --bundle-js=1 --minify-html=1

# Disable specific settings
bin/magento swissup:info:assets --merge-css=0 --minify-js=0
```

## Installation

### For clients

There are several ways to install extension for clients:

 1. If you've bought the product at Magento's Marketplace - use
    [Marketplace installation instructions](https://docs.magento.com/marketplace/user_guide/buyers/install-extension.html)
 2. Otherwise, you have two options:
    - Install the sources directly from [our repository](https://docs.swissuplabs.com/m2/extensions/diagnostic/installation/composer/) - **recommended**
    - Download archive and use [manual installation](https://docs.swissuplabs.com/m2/extensions/diagnostic/installation/manual/)

### For developers

Use this approach if you have access to our private repositories!

```bash
composer config repositories.swissup composer https://docs.swissuplabs.com/packages/
composer require swissup/module-diagnostic:dev-master --prefer-source
bin/magento setup:upgrade
```