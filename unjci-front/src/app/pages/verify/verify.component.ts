import { Component, ElementRef, OnDestroy, OnInit, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute } from '@angular/router';
import { BrowserQRCodeReader, IScannerControls } from '@zxing/browser';
import { MemberService } from '../../core/member.service';
import { MemberApplication } from '../../core/member.model';

@Component({selector:'app-verify',standalone:true,imports:[CommonModule,FormsModule],templateUrl:'./verify.component.html',styleUrl:'./verify.component.css'})
export class VerifyComponent implements OnInit,OnDestroy {
  @ViewChild('video') video?: ElementRef<HTMLVideoElement>;
  token=''; member?:MemberApplication; checked=false; scanning=false; error=''; controls?:IScannerControls;
  constructor(private route:ActivatedRoute,private members:MemberService){}
  ngOnInit(){const token=this.route.snapshot.paramMap.get('token');if(token){this.token=token;this.verify();}}
  verify(raw=this.token){
    const token=this.extractToken(raw); this.member=this.members.getByToken(token); this.checked=true; this.stopScanner();
  }
  extractToken(value:string){try{const url=new URL(value);return url.pathname.split('/').filter(Boolean).at(-1)||value;}catch{return value.trim();}}
  async startScanner(){
    this.error='';this.scanning=true;setTimeout(async()=>{try{const reader=new BrowserQRCodeReader();this.controls=await reader.decodeFromVideoDevice(undefined,this.video!.nativeElement,(result)=>{if(result)this.verify(result.getText());});}catch{this.error='Impossible d’accéder à la caméra. Vérifiez les autorisations ou utilisez la saisie manuelle.';this.scanning=false;}},0);
  }
  stopScanner(){this.controls?.stop();this.controls=undefined;this.scanning=false;}
  reset(){this.member=undefined;this.checked=false;this.token='';this.stopScanner();}
  ngOnDestroy(){this.stopScanner();}
}
