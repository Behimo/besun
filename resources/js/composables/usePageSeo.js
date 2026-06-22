import { BRAND, DEFAULT_SEO } from '@/config/brand'
import { computed, onUnmounted, unref, watch } from 'vue'

const setMetaTag = (attr, key, content) => {
  if (!content || typeof document === 'undefined')
    return

  let el = document.head.querySelector(`meta[${attr}="${key}"]`)

  if (!el) {
    el = document.createElement('meta')
    el.setAttribute(attr, key)
    document.head.appendChild(el)
  }

  el.setAttribute('content', content)
}

const setLinkTag = (rel, href) => {
  if (!href || typeof document === 'undefined')
    return

  let el = document.head.querySelector(`link[rel="${rel}"]`)

  if (!el) {
    el = document.createElement('link')
    el.setAttribute('rel', rel)
    document.head.appendChild(el)
  }

  el.setAttribute('href', href)
}

export const usePageSeo = options => {
  const opts = computed(() => {
    const raw = typeof options === 'function' ? options() : unref(options)

    return {
      title: raw?.title ?? DEFAULT_SEO.title,
      description: raw?.description ?? DEFAULT_SEO.description,
      keywords: raw?.keywords ?? DEFAULT_SEO.keywords,
      canonical: raw?.canonical,
      ogImage: raw?.ogImage ?? DEFAULT_SEO.ogImage,
      ogType: raw?.ogType ?? 'website',
      noindex: raw?.noindex ?? false,
      jsonLd: raw?.jsonLd,
    }
  })

  const apply = () => {
    const o = opts.value
    const base = BRAND.siteUrl.replace(/\/$/, '')

    document.title = o.title
    document.documentElement.lang = 'fa'
    document.documentElement.dir = 'rtl'

    setMetaTag('name', 'description', o.description)
    setMetaTag('name', 'keywords', o.keywords)
    setMetaTag('name', 'author', BRAND.nameEn)
    setMetaTag('name', 'robots', o.noindex ? 'noindex, nofollow' : 'index, follow')

    setMetaTag('property', 'og:locale', 'fa_IR')
    setMetaTag('property', 'og:site_name', BRAND.nameFa)
    setMetaTag('property', 'og:type', o.ogType)
    setMetaTag('property', 'og:title', o.title)
    setMetaTag('property', 'og:description', o.description)
    if (o.ogImage) {
      setMetaTag(
        'property',
        'og:image',
        o.ogImage.startsWith('http') ? o.ogImage : `${base}${o.ogImage}`,
      )
    }

    setMetaTag('name', 'twitter:card', 'summary_large_image')
    setMetaTag('name', 'twitter:title', o.title)
    setMetaTag('name', 'twitter:description', o.description)

    if (o.canonical)
      setLinkTag('canonical', o.canonical.startsWith('http') ? o.canonical : `${base}${o.canonical}`)

    const existing = document.getElementById('page-json-ld')
    if (existing)
      existing.remove()

    if (o.jsonLd) {
      const script = document.createElement('script')
      script.id = 'page-json-ld'
      script.type = 'application/ld+json'
      script.textContent = JSON.stringify(o.jsonLd)
      document.head.appendChild(script)
    }
  }

  watch(opts, apply, { immediate: true, deep: true })

  onUnmounted(() => {
    document.getElementById('page-json-ld')?.remove()
  })

  return { apply }
}
