/** Nano ID **/

/**
 * This alphabet uses `A-Za-z0-9_-` symbols.
 * The order of characters is optimized for better gzip and brotli compression.
 * References to the same file (works both for gzip and brotli):
 * `'use`, `andom`, and `rict'`
 * References to the brotli default dictionary:
 * `-26T`, `1983`, `40px`, `75px`, `bush`, `jack`, `mind`, `very`, and `wolf`
 */
const ID_CHARS = 'useandom-26T198340PX75pxJACKVERYMINDBUSHWOLF_GQZbfghjklqvwyzrict'
const ID_SIZE = 21

/**
 * Generates a unique ID, which provides the statistical equivalency of a UUID/GUID,
 * but as a much smaller size (21 characters vs 36). It is more useful in URLs.
 *
 * This code has been taken from the Nano ID library.
 * @see https://github.com/ai/nanoid
 */
function nanoId() {
    let id = ''
    let i = ID_SIZE
    while (i--) id += ID_CHARS[(Math.random() * 64) | 0]
    return id
}

function get_env() {
    return location.hostname.includes(".qa.") || location.hostname.includes("localhost") ? "dev" : "prod"
}

function get_kinesis_api() {
    return `https://affiliate-api${get_env() == 'dev' ? '.development' : ''}.raptive.com/v1/create/event`
}

function get_kinesis_stream() {
    return `apeng-event-stream-${get_env() == 'dev' ? 'development' : 'production'}`
}

async function send_analytics_event(event_name, event_data) {
    if (get_env() == 'dev') {
        console.log(event_name, event_data)
    }

    const kinesisData = {
        DeliveryStreamName: get_kinesis_stream(),
        Records: [{
            Data: btoa(JSON.stringify({
                type: event_name,
                id: nanoId(),
                timestamp: Date.now(),
                features: ["affiliate-plugin"],
                data: event_data
            }))
        }]
    }
    const body = JSON.stringify(kinesisData)

    try {
        // Post data to Kinesis endpoint
        const resp = await fetch(get_kinesis_api(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            mode: 'cors',
            body
        })
        if (!resp.ok) {
            console.error("Something went wrong persisting analytics events to Kinesis Firehose")
        }
    } catch (e) {
        console.error(e)
    }
}

function send_ingredient_match_event(plugin_version, site_domain, total_matches, selected) {
    let event = {
        plugin_version: plugin_version,
        site_domain: site_domain,
        total_matches: total_matches,
        selected: selected,
    }

    send_analytics_event('ingredient_match', event);
}

