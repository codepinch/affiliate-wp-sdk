# affiliate-wp-sdk
This affiliate SDK allows you to embed CodePinch installation link to your WordPress
plugin or theme.

See the codepinch-affiliate-sample to learn more how to integrate it.

View bullet-point:

- CodePinch SDK has to be bootstrapped during WordPress initialization to register backend installation page;
- Each affiliate has unique code that is used to generate the affiliate link;
- Do not show "Install CodePinch" link if plugin is already installed. Use  CodePinch_Affiliate::isInstalled() to check the status;
- In order to generate the affiliate link use CodePinch_Affiliate::getUrl('Your Affiliate Code').
