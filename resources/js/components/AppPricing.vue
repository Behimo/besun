<script setup>
import safeBoxWithGoldenCoin from '@images/misc/3d-safe-box-with-golden-dollar-coins.png'
import spaceRocket from '@images/misc/3d-space-rocket-with-smoke.png'
import dollarCoinPiggyBank from '@images/misc/dollar-coins-flying-pink-piggy-bank.png'
import { BRAND } from '@/config/brand'

const props = defineProps({
  title: { type: String, required: false },
  xs: { type: [Number, String], required: false },
  sm: { type: [Number, String], required: false },
  md: { type: [String, Number], required: false },
  lg: { type: [String, Number], required: false },
  xl: { type: [String, Number], required: false },
})

const apiPlans = ref([])

onMounted(async () => {
  try {
    const res = await $api('/plans')
    apiPlans.value = res.plans ?? []
  } catch {
    apiPlans.value = []
  }
})

const fallbackPlans = [
  {
    name: 'پلن ماهانه',
    tagLine: 'انعطاف برای شروع',
    logo: dollarCoinPiggyBank,
    price: 832200,
    duration: 'ماه',
    isPopular: false,
    features: ['ماژول پایه CRM', 'به ازای هر صندلی (کاربر)', 'قیف فروش و گزارش', 'چت تیم'],
  },
  {
    name: 'پلن ۶ ماهه',
    tagLine: '۱۰٪ تخفیف + پشتیبانی اولویت',
    logo: safeBoxWithGoldenCoin,
    price: 4161000,
    duration: '۶ ماه',
    isPopular: true,
    features: ['همه امکانات پایه', 'تخفیف ۶ ماهه', 'پشتیبانی اولویت‌دار', 'ماژول‌های افزونه'],
  },
  {
    name: 'پلن سالانه',
    tagLine: '۲۰٪ تخفیف + پشتیبانی VIP',
    logo: spaceRocket,
    price: 7489800,
    duration: 'سال',
    isPopular: false,
    features: ['همه امکانات پایه', 'بیشترین صرفه‌جویی', 'پشتیبانی VIP', 'اولویت feature'],
  },
]

const displayPlans = computed(() => {
  if (apiPlans.value.length) {
    const logos = [dollarCoinPiggyBank, safeBoxWithGoldenCoin, spaceRocket]

    return apiPlans.value.map((plan, index) => ({
      name: plan.name,
      tagLine: plan.features?.[0] ?? '',
      logo: logos[index % logos.length],
      price: plan.price,
      duration: `${plan.duration_months} ماه`,
      isPopular: index === 1,
      features: plan.features ?? [],
    }))
  }

  return fallbackPlans
})
</script>

<template>
  <div class="text-center">
    <h2 class="text-h3 pricing-title mb-2">
      {{ props.title || `قیمت‌گذاری ${BRAND.nameFa}` }}
    </h2>
    <p class="mb-0 text-medium-emphasis">
      ماژول پایه CRM بر اساس تعداد صندلی (کاربر) — {{ BRAND.trialDays }} روز آزمایش رایگان
    </p>
  </div>

  <VRow class="mt-12">
    <VCol
      v-for="plan in displayPlans"
      :key="plan.name"
      v-bind="props"
      cols="12"
    >
      <VCard
        flat
        border
        :class="plan.isPopular ? 'border-primary border-opacity-100' : ''"
      >
        <VCardText
          style="block-size: 3.75rem;"
          class="text-end"
        >
          <VChip
            v-show="plan.isPopular"
            label
            color="primary"
            size="small"
          >
            پیشنهادی
          </VChip>
        </VCardText>

        <VCardText>
          <VImg
            :height="100"
            :width="100"
            :src="plan.logo"
            class="mx-auto mb-5"
          />

          <h3 class="text-h4 mb-1 text-center">
            {{ plan.name }}
          </h3>
          <p class="mb-4 text-body-1 text-center text-medium-emphasis">
            {{ plan.tagLine }}
          </p>

          <div class="d-flex justify-center align-end pb-8 gap-1">
            <h2 class="text-h2 font-weight-medium text-primary">
              {{ Number(plan.price).toLocaleString('fa-IR') }}
            </h2>
            <span class="text-body-2 pb-1">ریال / {{ plan.duration }}</span>
          </div>

          <VList class="card-list mb-4">
            <VListItem
              v-for="feature in plan.features"
              :key="feature"
            >
              <template #prepend>
                <VIcon
                  size="8"
                  icon="tabler-circle-filled"
                  color="primary"
                />
              </template>
              <VListItemTitle class="text-body-1">
                {{ feature }}
              </VListItemTitle>
            </VListItem>
          </VList>

          <VBtn
            block
            :color="plan.isPopular ? 'primary' : 'secondary'"
            :variant="plan.isPopular ? 'elevated' : 'tonal'"
            :to="{ name: 'login' }"
          >
            شروع {{ BRAND.trialDays }} روز رایگان
          </VBtn>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style lang="scss" scoped>
.card-list {
  --v-card-list-gap: 0.75rem;
}
</style>
