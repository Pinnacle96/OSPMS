import {useEffect,useState} from 'react';
const prefix='ospm.notification-preferences.v1:';
export function preferenceKey(account:string){return prefix+account;}
export function readBadge(account:string):boolean {try {return JSON.parse(localStorage.getItem(preferenceKey(account))??'{}').showUnreadBadge!==false;}catch{return true;}}
export default function useNotificationPreferences(account:string){const [show,setShow]=useState(()=>readBadge(account));useEffect(()=>{const update=()=>setShow(readBadge(account));update();window.addEventListener('storage',update);window.addEventListener('ospm-notification-preferences',update);return()=>{window.removeEventListener('storage',update);window.removeEventListener('ospm-notification-preferences',update);};},[account]);return show;}
