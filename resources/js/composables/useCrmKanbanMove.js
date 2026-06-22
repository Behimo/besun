export const applyKanbanMove = (stages, { itemId, targetStageId, collectionKey }) => {
  const snapshot = JSON.parse(JSON.stringify(stages))
  let movedItem = null

  for (const stage of stages) {
    const items = stage[collectionKey] ?? []

    stage[collectionKey] = items.filter(item => {
      if (item.id === itemId) {
        movedItem = item

        return false
      }

      return true
    })
  }

  if (!movedItem)
    return snapshot

  const target = stages.find(stage => stage.id === targetStageId)

  if (target) {
    target[collectionKey] = target[collectionKey] ?? []
    target[collectionKey].unshift(movedItem)
  }

  return snapshot
}

export const useCrmKanbanMove = (stagesRef, options = {}) => {
  const {
    collectionKey = 'deals',
    stageEndpoint = id => `/deals/${id}/stage`,
    stageBody = targetStageId => ({ pipeline_stage_id: targetStageId }),
  } = options

  const onMove = async ({ itemId, targetStageId }) => {
    const snapshot = applyKanbanMove(stagesRef.value, {
      itemId,
      targetStageId,
      collectionKey,
    })

    try {
      await $api(stageEndpoint(itemId), {
        method: 'PATCH',
        body: stageBody(targetStageId),
      })
    } catch (error) {
      console.error(error)
      stagesRef.value = snapshot
    }
  }

  return { onMove, applyKanbanMove }
}
