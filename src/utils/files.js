import { generateRemoteUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'

const davRequest = `<?xml version="1.0"?>
<d:propfind xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns" xmlns:nc="http://nextcloud.org/ns">
  <d:prop><oc:fileid /><d:getcontenttype /></d:prop>
</d:propfind>`

const getFileId = (xml) => {
  if (window.DOMParser) {
    const parser = new DOMParser()
    const xmlDoc = parser.parseFromString(xml, 'text/xml')
    const el = xmlDoc.getElementsByTagName('oc:fileid')[0]
    return el ? parseInt(el.innerHTML) : -1
  }
  return -1
}

const requestFileInfo = async (path) => {
  const davPath = `${generateRemoteUrl('dav')}/files/${getCurrentUser().uid}${path}`
  return await axios({ method: 'PROPFIND', url: davPath, data: davRequest, headers: { details: true, depth: 0 } })
}

export { requestFileInfo, getFileId }
