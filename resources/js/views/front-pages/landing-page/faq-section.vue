<script setup>
import boyWithLogo from '@images/front-pages/landing-page/faq-boy-with-logos.png'
import { BRAND } from '@/config/brand'

const faqData = [
  {
    question: `${BRAND.nameFa} چیست و برای چه کسب‌وکارهایی مناسب است؟`,
    answer: `${BRAND.nameFa} (${BRAND.nameEn}) یک پلتفرم SaaS فارسی برای مدیریت لید، معامله، کمپین و تیم فروش است. از فریلنسر B2B تا شرکت‌های چندشعبه با Workspace جدا مناسب است.`,
  },
  {
    question: 'آیا دوره آزمایشی رایگان دارید؟',
    answer: `بله — ${BRAND.trialDays} روز دسترسی کامل بدون نیاز به کارت بانکی. پس از آن پلن ماهانه، ۶ماهه یا سالانه را انتخاب کنید.`,
  },
  {
    question: 'گزارش فروش در پلن پایه شامل چه مواردی است؟',
    answer: 'ارزش معاملات در هر مرحله، نرخ ریزش قیف، عملکرد کمپین، هزینه هر لید و مقایسه عملکرد فروشندگان — همه در ماژول پایه CRM.',
  },
  {
    question: 'داده‌های من چقدر امن است؟',
    answer: 'هر مجموعه (Tenant) داده جدا دارد. دسترسی بر اساس نقش کنترل می‌شود و Workspaceها از هم ایزوله هستند.',
  },
  {
    question: 'آیا می‌توانم ماژول اضافه بخرم؟',
    answer: 'بله — پیامک، اتوماسیون، BI، فاکتور و یکپارچگی را از فروشگاه ماژول داخل پنل فعال کنید.',
  },
]
</script>

<template>
  <div id="faq">
    <VContainer>
      <div class="faq-section">
        <div class="headers d-flex justify-center flex-column align-center">
          <VChip
            label
            color="primary"
            size="small"
            class="mb-4"
          >
            سوالات متداول
          </VChip>
          <h2 class="d-flex align-center text-h4 mb-1 flex-wrap justify-center">
            پاسخ
            <div class="position-relative ms-2">
              <div class="section-title">
                سوالات
              </div>
            </div>
            رایج
          </h2>
          <p class="text-body-1 mb-0">
            درباره {{ BRAND.nameFa }} و {{ BRAND.nameEn }}
          </p>
        </div>
        <VRow>
          <VCol
            cols="12"
            md="5"
          >
            <div class="pt-10 d-flex align-center justify-center h-100">
              <VImg
                :src="boyWithLogo"
                height="330"
                width="330"
                alt="سوالات متداول راهبر CRM"
              />
            </div>
          </VCol>
          <VCol
            cols="12"
            md="7"
          >
            <VExpansionPanels class="pt-16">
              <VExpansionPanel
                v-for="faq in faqData"
                :key="faq.question"
              >
                <VExpansionPanelTitle>{{ faq.question }}</VExpansionPanelTitle>
                <VExpansionPanelText>{{ faq.answer }}</VExpansionPanelText>
              </VExpansionPanel>
            </VExpansionPanels>
          </VCol>
        </VRow>
      </div>
    </VContainer>
  </div>
</template>

<style lang="scss" scoped>
.faq-section {
  margin-block: 5.25rem;
}

#faq {
  border-radius: 3.75rem 3.75rem 0 0;
  background-color: rgba(var(--v-theme-background));
}

.section-title {
  font-size: 24px;
  font-weight: 800;
  line-height: 36px;
}

.section-title::after {
  position: absolute;
  background: url("../../../assets/images/front-pages/icons/section-title-icon.png") no-repeat left bottom;
  background-size: contain;
  block-size: 100%;
  content: "";
  font-weight: 800;
  inline-size: 130%;
  inset-block-end: 12%;
  inset-inline-start: -12%;
}
</style>
