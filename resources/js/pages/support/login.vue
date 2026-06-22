<script setup>
import PlatformStaffLoginForm from '@/components/auth/PlatformStaffLoginForm.vue'

definePage({
  meta: {
    layout: 'blank',
    unauthenticatedOnly: true,
    platformPortal: true,
    portal: 'support',
  },
})
</script>

<template>
  <PlatformStaffLoginForm
    portal="support"
    title="ورود پشتیبانی"
    subtitle="تیم پشتیبانی پلتفرم رهبر CRM"
  />
</template>
