<script setup>
import ConnectImg from '@images/front-pages/landing-page/contact-customer-service.png'
import { BRAND } from '@/config/brand'

const name = ref('')
const email = ref('')
const message = ref('')
const phone = ref('')
const submitting = ref(false)
const success = ref(false)

const submit = async () => {
  submitting.value = true
  success.value = false
  try {
    await $api('/leads/contact', {
      method: 'POST',
      body: {
        name: name.value,
        email: email.value,
        phone: phone.value,
        message: message.value,
      },
    })
    success.value = true
    name.value = ''
    email.value = ''
    phone.value = ''
    message.value = ''
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <VContainer id="contact-us">
    <!-- 👉 Headers  -->
    <div class="contact-us-section">
      <div class="headers d-flex justify-center flex-column align-center pb-16">
        <VChip
          label
          color="primary"
          class="mb-4"
          size="small"
        >
          تماس با ما
        </VChip>
        <h4 class="d-flex align-center text-h4 mb-1 flex-wrap justify-center">
          <div class="position-relative me-2">
            <div class="section-title">
              با ما
            </div>
          </div>
          در تماس باشید
        </h4>
        <p class="text-body-1 mb-0">
          سوال فروش، پشتیبانی یا دمو {{ BRAND.nameFa }} — پیام بفرستید
        </p>
      </div>

      <div class="mb-15">
        <VRow class="match-height">
          <VCol
            cols="12"
            md="5"
          >
            <div class="contact-card h-100">
              <VCard
                variant="outlined"
                border
                class="pa-2"
                :style="{ borderRadius: '3.75rem 0.375rem 0.375rem 0.375rem' }"
              >
                <VImg
                  :src="ConnectImg"
                  :style="{ borderRadius: '3.75rem 0.375rem 0.375rem 0.375rem' }"
                />
                <VCardText class="pa-4 pb-1">
                  <div class="d-flex justify-space-between flex-wrap gap-y-4">
                    <div
                      v-for="(item, index) in [
                        { title: 'ایمیل', icon: 'tabler-mail', color: 'primary', value: 'info@rahbarcrm.ir' },
                        { title: 'تلفن', icon: 'tabler-phone-call', color: 'success', value: '021-12345678' },
                      ]"
                      :key="index"
                      class="d-flex gap-x-3 align-center"
                    >
                      <div>
                        <VAvatar
                          size="36"
                          :color="item.color"
                          variant="tonal"
                          class="rounded-sm"
                        >
                          <VIcon
                            :icon="item.icon"
                            size="24"
                          />
                        </VAvatar>
                      </div>

                      <div>
                        <div class="text-body-1">
                          {{ item .title }}
                        </div>
                        <h6 class="text-h6">
                          {{ item.value }}
                        </h6>
                      </div>
                    </div>
                  </div>
                </VCardText>
              </VCard>
            </div>
          </VCol>

          <VCol
            cols="12"
            md="7"
          >
            <VCard>
              <VCardItem class="pb-0">
                <h4 class="text-h4 mb-1">
                  ارسال پیام
                </h4>
              </VCardItem>

              <VCardText>
                <VAlert
                  v-if="success"
                  type="success"
                  variant="tonal"
                  class="mb-4"
                >
                  پیام شما با موفقیت ثبت شد.
                </VAlert>
                <VForm @submit.prevent="submit">
                  <VRow>
                    <VCol
                      cols="12"
                      md="6"
                    >
                      <AppTextField
                        v-model="name"
                        placeholder="نام کامل"
                        label="نام"
                      />
                    </VCol>

                    <VCol
                      cols="12"
                      md="6"
                    >
                      <AppTextField
                        v-model="email"
                        placeholder="email@example.com"
                        label="ایمیل"
                      />
                    </VCol>

                    <VCol cols="12">
                      <AppTextField
                        v-model="phone"
                        label="تلفن"
                      />
                    </VCol>

                    <VCol cols="12">
                      <AppTextarea
                        v-model="message"
                        placeholder="پیام خود را بنویسید"
                        rows="3"
                        label="پیام"
                      />
                    </VCol>

                    <VCol>
                      <VBtn
                        type="submit"
                        :loading="submitting"
                      >
                        ارسال
                      </VBtn>
                    </VCol>
                  </VRow>
                </VForm>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </div>
    </div>
  </VContainer>
</template>

<style lang="scss" scoped>
.contact-us-section {
  margin-block: 5.25rem;
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
  inline-size: 120%;
  inset-block-end: 12%;
  inset-inline-start: -12%;
}

.contact-card {
  position: relative;
}

.contact-card::before {
  position: absolute;
  content: url("@images/front-pages/icons/contact-border.png");
  inset-block-start: -2.5rem;
  inset-inline-start: -2.5rem;
}

@media screen and (max-width: 999px) {
  .contact-card::before {
    display: none;
  }
}
</style>
