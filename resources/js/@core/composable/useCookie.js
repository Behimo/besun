// Ported from [Nuxt](https://github.com/nuxt/nuxt/blob/main/packages/nuxt/src/app/composables/cookie.ts)
import { parse, serialize } from 'cookie-es'
import { destr } from 'destr'

const CookieDefaults = {
  path: '/',
  watch: true,
  decode: val => destr(decodeURIComponent(val)),
  encode: val => encodeURIComponent(typeof val === 'string' ? val : JSON.stringify(val)),
}

const AUTH_COOKIE_NAMES = new Set([
  'accessToken',
  'userData',
  'userAbilityRules',
  'authSessionExpiresAt',
])

/** مدت نگهداری کوکی‌های احراز هویت: ۱ ساعت */
export const AUTH_COOKIE_MAX_AGE = 60 * 60

const DEFAULT_COOKIE_MAX_AGE = 60 * 60 * 24 * 30

const _cookieRefCache = new Map()

export const useCookie = (name, _opts) => {
  const opts = { ...CookieDefaults, ..._opts || {} }

  if (AUTH_COOKIE_NAMES.has(name))
    opts.maxAge = AUTH_COOKIE_MAX_AGE

  const cacheKey = AUTH_COOKIE_NAMES.has(name) ? name : `${name}::${opts.maxAge ?? DEFAULT_COOKIE_MAX_AGE}`

  if (_cookieRefCache.has(cacheKey))
    return _cookieRefCache.get(cacheKey)

  const cookies = parse(document.cookie, opts)
  const cookie = ref(cookies[name] ?? opts.default?.())

  watch(cookie, () => {
    document.cookie = serializeCookie(name, cookie.value, opts)
  })

  _cookieRefCache.set(cacheKey, cookie)

  return cookie
}
function serializeCookie(name, value, opts = {}) {
  if (value === null || value === undefined)
    return serialize(name, value, { ...opts, maxAge: -1 })

  const maxAge = AUTH_COOKIE_NAMES.has(name)
    ? AUTH_COOKIE_MAX_AGE
    : (opts.maxAge ?? DEFAULT_COOKIE_MAX_AGE)

  return serialize(name, value, { ...opts, maxAge })
}
