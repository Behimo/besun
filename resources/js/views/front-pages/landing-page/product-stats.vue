<script setup>
import check from '@images/svg/Check.svg'
import diamond from '@images/svg/Diamond.svg'
import laptop from '@images/svg/laptop.svg'
import user from '@images/svg/user.svg'

const statData = [
  {
    title: 'ماژول CRM',
    value: '۱۲+',
    icon: laptop,
    color: 'primary',
    desc: 'فروش، بازاریابی، گزارش',
  },
  {
    title: 'نقش کاربری',
    value: '۴',
    icon: user,
    color: 'success',
    desc: 'Owner تا Employee',
  },
  {
    title: 'قیف فروش',
    value: '۵ مرحله',
    icon: diamond,
    color: 'info',
    desc: 'قابل سفارشی‌سازی',
  },
  {
    title: 'آزمایش رایگان',
    value: '۱۴ روز',
    icon: check,
    color: 'warning',
    desc: 'بدون کارت بانکی',
  },
]
</script>

<template>
  <div :style="{ 'background-color': 'rgb(var(--v-theme-surface))' }">
    <VContainer>
      <div class="py-12">
        <VRow>
          <VCol
            v-for="(product, index) in statData"
            :key="index"
            cols="12"
            sm="6"
            lg="3"
          >
            <VCard
              flat
              :style="{ border: `1px solid rgba(var(--v-theme-${product.color}))` }"
            >
              <VCardText class="text-center">
                <VIcon
                  :color="product.color"
                  :icon="product.icon"
                  size="64"
                  class="mb-4"
                />
                <h3 class="text-h3">
                  {{ product.value }}
                </h3>
                <p class="text-body-1 font-weight-medium mb-1 text-wrap">
                  {{ product.title }}
                </p>
                <p class="text-caption text-medium-emphasis mb-0">
                  {{ product.desc }}
                </p>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </div>
    </VContainer>
  </div>
</template>
