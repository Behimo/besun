<script setup>
import check from '@images/svg/Check.svg'
import keyboard from '@images/svg/keyboard.svg'
import laptop from '@images/svg/laptop.svg'
import paper from '@images/svg/paper-send.svg'
import rocket from '@images/svg/rocket.svg'
import user from '@images/svg/user.svg'

const featuresData = [
  {
    title: 'قیف فروش و معاملات',
    desc: 'کاریز drag & drop، مراحل سفارشی، مبلغ معامله و پیگیری تا بستن قرارداد.',
    icon: laptop,
  },
  {
    title: 'بازاریابی و کمپین',
    desc: 'کمپین، لید، قیف بازاریابی و تبدیل لید به مخاطب و معامله.',
    icon: rocket,
  },
  {
    title: 'گزارش فروش و تیم',
    desc: 'ارزش هر مرحله، نرخ ریزش، عملکرد کمپین و مقایسه فروشندگان — در پلن پایه.',
    icon: keyboard,
  },
  {
    title: 'چند مجموعه (Tenant)',
    desc: 'هر کسب‌وکار Workspace جدا؛ داده‌ها کاملاً ایزوله و امن.',
    icon: check,
  },
  {
    title: 'نقش و دسترسی',
    desc: 'Owner، Admin، Employee — کنترل دقیق بر ماژول‌ها و تنظیمات.',
    icon: user,
  },
  {
    title: 'ماژول‌های تکمیلی',
    desc: 'پیامک، اتوماسیون، BI، فاکتور و یکپارچگی — فعال‌سازی در فروشگاه ماژول.',
    icon: paper,
  },
]
</script>

<template>
  <VContainer id="features">
    <div class="feature-cards">
      <div class="headers d-flex justify-center flex-column align-center mb-12">
        <VChip
          label
          color="primary"
          class="mb-4"
          size="small"
        >
          امکانات
        </VChip>
        <h2 class="d-flex text-h4 mb-1 align-center flex-wrap justify-center">
          <div class="position-relative me-2">
            <div class="section-title">
              همه ابزار
            </div>
          </div>
          فروش و بازاریابی
        </h2>
        <p class="text-body-1 mb-0 text-center text-medium-emphasis">
          راهبر CRM از ثبت لید تا گزارش درآمد — بدون پراکندگی در اکسل و چت‌های شخصی.
        </p>
      </div>
      <VRow>
        <VCol
          v-for="(data, index) in featuresData"
          :key="index"
          cols="12"
          md="4"
          sm="6"
        >
          <div class="d-flex flex-column align-center justify-center gap-4 mx-auto">
            <VIcon
              :icon="data.icon"
              size="64"
              color="primary"
            />
            <div class="text-center">
              <h3 class="text-h5 mb-2">
                {{ data.title }}
              </h3>
              <p
                class="text-body-1 mb-6 text-medium-emphasis"
                style="max-inline-size: 310px;"
              >
                {{ data.desc }}
              </p>
            </div>
          </div>
        </VCol>
      </VRow>
    </div>
  </VContainer>
</template>

<style lang="scss" scoped>
.feature-cards {
  margin-block: 3.75rem;
}

.section-title::after {
  position: absolute;
  background: url("../../../assets/images/front-pages/icons/section-title-icon.png") no-repeat left bottom;
  background-size: contain;
  block-size: 100%;
  content: "";
  inline-size: 120%;
  inset-block-end: 0;
  inset-inline-start: -12%;
}

.section-title {
  font-size: 24px;
  font-weight: 800;
  line-height: 36px;
}
</style>
