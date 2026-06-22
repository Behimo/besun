<script setup>
const userData = useCookie('userData')
const { applyAuthPayload } = useAppShell()

const workspaces = ref([])
const loading = ref(false)

const currentName = computed(() => userData.value?.workspace?.name ?? 'فضای کاری')

const fetchWorkspaces = async () => {
  loading.value = true
  try {
    const res = await $api('/workspaces')
    workspaces.value = res.workspaces ?? []
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

const switchWorkspace = async workspace => {
  const res = await $api(`/workspaces/${workspace.id}/switch`, { method: 'POST' })
  applyAuthPayload(res)
}

onMounted(fetchWorkspaces)
</script>

<template>
  <VMenu>
    <template #activator="{ props }">
      <VBtn
        v-bind="props"
        variant="tonal"
        size="small"
        prepend-icon="tabler-building-warehouse"
        :loading="loading"
      >
        {{ currentName }}
      </VBtn>
    </template>
    <VList>
      <VListItem
        v-for="ws in workspaces"
        :key="ws.id"
        :title="ws.name"
        @click="switchWorkspace(ws)"
      />
    </VList>
  </VMenu>
</template>
