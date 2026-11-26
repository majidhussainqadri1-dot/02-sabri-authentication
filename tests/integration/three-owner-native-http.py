"""Disposable-only native form journey; no test hooks or session synthesis."""
import http.cookiejar
import json
import os
import re
import sys
import urllib.parse
import urllib.request
from html.parser import HTMLParser

ORIGIN = "http://127.0.0.1:8765"
PLUGIN = "sabri-platform-foundation/sabri-platform-foundation.php"
UA = "Sabri-Disposable-Integration/1.0"
if os.environ.get("SAUTH_DISPOSABLE_THREE_OWNER_QA") != "1":
    raise SystemExit("Refusing non-disposable native test")
password = os.environ.get("CI_NATIVE_TEST_PASSWORD", "")
phase = sys.argv[1] if len(sys.argv) == 2 else ""
page = os.environ.get("CI_NATIVE_LOGIN_PAGE", "")
if phase not in ("bootstrap", "activate") or not re.fullmatch(r"[a-f0-9]{48}", password):
    raise SystemExit("Disposable test prerequisites absent")
if phase == "activate" and (not page.isdigit() or int(page) <= 0):
    raise SystemExit("Native login page prerequisite absent")

def safe_url(url):
    parts = urllib.parse.urlsplit(url)
    if parts.scheme != "http" or parts.netloc != "127.0.0.1:8765":
        raise RuntimeError("Non-loopback destination refused")
    return url

class LocalRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return super().redirect_request(req, fp, code, msg, headers, safe_url(newurl))

class Markup(HTMLParser):
    def __init__(self, text):
        super().__init__(convert_charrefs=True)
        self.inputs = {}
        self.links = []
        self.feed(text)
    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag == "input" and a.get("name"):
            self.inputs[a["name"]] = a.get("value", "")
        if tag == "a" and a.get("href"):
            self.links.append(a["href"])

jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(
    urllib.request.ProxyHandler({}), LocalRedirect(),
    urllib.request.HTTPCookieProcessor(jar),
)
def request(path, fields=None):
    url = safe_url(urllib.parse.urljoin(ORIGIN + "/", path))
    data = None if fields is None else urllib.parse.urlencode(fields).encode()
    req = urllib.request.Request(url, data=data, headers={"User-Agent": UA})
    with opener.open(req, timeout=45) as res:
        return safe_url(res.geturl()), res.read(4 * 1024 * 1024).decode("utf-8", "replace")

def activation_link(url, html):
    for href in Markup(html).links:
        absolute = urllib.parse.urljoin(url, href)
        p = urllib.parse.urlsplit(absolute)
        if p.scheme != "http" or p.netloc != "127.0.0.1:8765":
            continue
        q = urllib.parse.parse_qs(p.query)
        if p.path == "/wp-admin/plugins.php" and q.get("action") == ["activate"] and q.get("plugin") == [PLUGIN] and q.get("_wpnonce"):
            return absolute
    return None

try:
    if phase == "bootstrap":
        request("/wp-login.php")
        request("/wp-login.php", {
            "log": "founder", "pwd": password, "wp-submit": "Log In",
            "redirect_to": ORIGIN + "/wp-admin/", "testcookie": "1",
        })
        if not any(c.name.startswith("wordpress_logged_in_") for c in jar):
            raise RuntimeError("Native core login before File02 did not establish a real browser cookie")
        url, html = request("/wp-admin/plugins.php")
        if not activation_link(url, html):
            raise RuntimeError("Native File00 admin bootstrap lacks existing activation authority")
        print("PASS native core admin request triggered File00 deferred bootstrap")
        sys.exit(0)
    url, html = request("/wp-admin/plugins.php")
    if activation_link(url, html):
        raise RuntimeError("Unauthenticated actor received activation link")
    print("PASS guest denied native plugin activation surface")
    dest = ORIGIN + "/wp-admin/plugins.php"
    url, html = request("/?" + urllib.parse.urlencode({"page_id": page, "redirect_to": dest}))
    nonce = Markup(html).inputs.get("sa_nonce", "")
    if not nonce:
        raise RuntimeError("Native File02 login form unavailable")
    request("/wp-admin/admin-post.php", {
        "action": "sa_login", "sa_nonce": nonce, "redirect_to": dest,
        "user_login": "founder", "password": password, "website": "",
        "_wp_http_referer": "/?" + urllib.parse.urlencode({"page_id": page}),
    })
    del password
    if not any(c.name.startswith("wordpress_logged_in_") for c in jar):
        raise RuntimeError("Native File02 sign-in did not establish a real browser cookie")
    print("PASS native File02 password form established browser cookie")
    url, html = request("/wp-admin/plugins.php")
    link = activation_link(url, html)
    if not link:
        raise RuntimeError("Native authenticated actor did not receive authorized File01 activation link")
    print("PASS native WordPress admin capability and activation nonce available")
    request(link)
    url, html = request("/wp-admin/plugins.php")
    if activation_link(url, html):
        raise RuntimeError("File01 still inactive on native plugins page")
    print("PASS native File01 activation request completed; independent WP-CLI DB postcondition follows")
    expected = {
        "register_manifest", "register_contract", "acknowledge_contract",
        "map_route", "repair_owned_mapping", "set_flag", "system_check",
        "arbitrary_unregistered_action",
    }
    def claims():
        _, body = request("/sauth-native-claims-ci.php")
        result = json.loads(body)
        if not isinstance(result, dict) or set(result.get("decisions", {})) != expected:
            raise RuntimeError("Native fixed decision inventory unavailable")
        return result
    founder = claims()
    if founder.get("actor") != "founder" or founder.get("session_present") is not True:
        raise RuntimeError("Native Founder cookie not retained by all three modules")
    if any(founder["decisions"][key] is not (key != "arbitrary_unregistered_action") for key in expected):
        raise RuntimeError("Native Founder decision matrix differs from canonical policy")
    print("PASS actual authenticated Founder: seven scoped decisions allowed, unknown action denied")
    jar.clear()
    guest = claims()
    if guest.get("actor") != "guest" or guest.get("session_present") is not False:
        raise RuntimeError("Fresh guest context did not remain unauthenticated")
    if any(guest["decisions"][key] is not False for key in expected):
        raise RuntimeError("Guest received a protected File01 decision")
    print("PASS actual guest: all eight fixed decisions denied; no mutation executed")
except Exception as exc:
    # No response bodies, credentials, session cookies, or nonce values in logs.
    print("FAIL native three-owner journey: " + type(exc).__name__ + ": " + (
        str(exc) if isinstance(exc, RuntimeError) else "request or response failed"
    ), file=sys.stderr)
    sys.exit(1)
finally:
    jar.clear()
