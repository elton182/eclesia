import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import {
  assessCameraAccess,
  assessGalleryAccess,
  CAMERA_PERMISSION_HELP,
  GALLERY_PERMISSION_HELP,
  queryCameraPermission,
} from './mediaPermissions.js'

describe('mediaPermissions', () => {
  it('queryCameraPermission lê granted/denied/prompt ou unknown', async () => {
    assert.equal(await queryCameraPermission({ permissions: null }), 'unknown')

    assert.equal(
      await queryCameraPermission({
        permissions: {
          query: async () => ({ state: 'denied' }),
        },
      }),
      'denied',
    )

    assert.equal(
      await queryCameraPermission({
        permissions: {
          query: async () => {
            throw new Error('unsupported')
          },
        },
      }),
      'unknown',
    )
  })

  it('assessCameraAccess bloqueia quando denied', async () => {
    const denied = await assessCameraAccess({
      permissions: { query: async () => ({ state: 'denied' }) },
    })
    assert.equal(denied.blocked, true)
    assert.match(denied.help, /Câmera/)

    const granted = await assessCameraAccess({
      permissions: { query: async () => ({ state: 'granted' }) },
    })
    assert.equal(granted.blocked, false)

    const probed = await assessCameraAccess({
      permissions: {
        query: async () => {
          throw new Error('nope')
        },
      },
      probeIfUnknown: true,
      mediaDevices: {
        getUserMedia: async () => {
          const err = new Error('no')
          err.name = 'NotAllowedError'
          throw err
        },
      },
    })
    assert.equal(probed.blocked, true)
    assert.equal(CAMERA_PERMISSION_HELP.length > 20, true)
  })

  it('assessGalleryAccess não bloqueia por padrão (só ajuda)', async () => {
    const g = await assessGalleryAccess()
    assert.equal(g.blocked, false)
    assert.match(g.help, /Fotos/)
    assert.equal(GALLERY_PERMISSION_HELP.includes('Permissões'), true)
  })
})
