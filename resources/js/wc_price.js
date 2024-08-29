
function wc_price_js(price, args = {}) {
    const defaultArgs = {
        currency_symbol: wc_settings_args.currency_symbol,
        decimal_separator: wc_settings_args.decimal_separator,
        thousand_separator: wc_settings_args.thousand_separator,
        decimals: wc_settings_args.currency_format_num_decimals,
        price_format: wc_settings_args.price_format
    };

    const settings = {...defaultArgs, ...args};

    price = parseFloat(price);
    price = price.toFixed(settings.decimals);
    price = price.replace('.', settings.decimal_separator);
    price = price.replace(/\B(?=(\d{3})+(?!\d))/g, settings.thousand_separator);

    return settings.price_format
        .replace('%1$s', settings.currency_symbol)
        .replace('%2$s', price);
}
