import { getI18n } from '@/plugins/i18n/index'
import { deepMerge } from '@antfu/utils'
import { themeConfig } from '@themeConfig'
import { useI18n } from 'vue-i18n'
import { createVuetify } from 'vuetify'
import { VBtn } from 'vuetify/components/VBtn'
import { VVideo } from 'vuetify/labs/VVideo'
import { createVueI18nAdapter } from 'vuetify/locale/adapters/vue-i18n'
import defaults from './defaults'
import { icons } from './icons'
import {
  staticDarkPrimaryColor,
  staticDarkPrimaryDarkenColor,
  staticPrimaryColor,
  staticPrimaryDarkenColor,
  themes,
} from './theme'

// Styles
import { cookieRef } from '@/@layouts/stores/config'
import '@core-scss/template/libs/vuetify/index.scss'
import 'vuetify/styles'

const resolveThemeColor = (cookieName, fallback, legacyValues = []) => {
  const storedColor = cookieRef(cookieName, null)

  if (legacyValues.includes(storedColor.value)) {
    storedColor.value = null

    return fallback
  }

  return storedColor.value ?? fallback
}

export default function (app) {
  const cookieThemeValues = {
    defaultTheme: resolveVuetifyTheme(themeConfig.app.theme),
    themes: {
      light: {
        colors: {
          'primary': resolveThemeColor('lightThemePrimaryColor', staticPrimaryColor, ['#7367F0']),
          'primary-darken-1': resolveThemeColor('lightThemePrimaryDarkenColor', staticPrimaryDarkenColor, ['#675DD8']),
        },
      },
      dark: {
        colors: {
          'primary': resolveThemeColor('darkThemePrimaryColor', staticDarkPrimaryColor, ['#7367F0']),
          'primary-darken-1': resolveThemeColor('darkThemePrimaryDarkenColor', staticDarkPrimaryDarkenColor, ['#675DD8']),
        },
      },
    },
  }

  const optionTheme = deepMerge({ themes }, cookieThemeValues)

  const vuetify = createVuetify({
    aliases: {
      IconBtn: VBtn,
    },
    components: {
      VVideo,
    },
    defaults,
    icons,
    theme: optionTheme,
    locale: {
      adapter: createVueI18nAdapter({ i18n: getI18n(), useI18n }),
    },
  })

  app.use(vuetify)
}
