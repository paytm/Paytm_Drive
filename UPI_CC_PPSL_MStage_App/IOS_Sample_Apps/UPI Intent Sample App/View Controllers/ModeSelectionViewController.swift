//
//  ModeSelectionViewController.swift
//  UPI Intent Sample App
//
//  Created by Vishrut tewatia on 07/10/24.
//

import UIKit

protocol ModeSelectionProtocol: AnyObject {
    func resetDict()
}

class ModeSelectionViewController: UIViewController, ModeSelectionProtocol {
    
    //MARK: Outlets

    @IBOutlet weak var intentSampleAppBtn: UIButton!
    @IBOutlet weak var mstageQrAppBtn: UIButton! {
        didSet {
            mstageQrAppBtn.layer.cornerRadius = 8
        }
    }
    
    //MARK: Constants
    
    var extSerialNo: String = ""
    var amount: String = ""
    var url: String = ""
    var isDeeplinkForIntentApp: Bool? = false
    var mscanDict: [String: String]? = nil
    
    //MARK: Lifecycle Methods
    
    override func viewDidLoad() {
        super.viewDidLoad()
    }
    
    //MARK: Intent Sample App Params Update Method
    
    func updateVariablesForIntentSampleAppInvoke(extSerialNo: String, amount: String, url: String) {
        self.extSerialNo = extSerialNo
        self.amount = amount
        self.url = url
        intentSampleAppBtn.sendActions(for: .touchUpInside)
    }
    
    func updateVariablesForMscanSampleAppInvoke( dict: [String: String]) {
        self.mscanDict = dict
        mstageQrAppBtn.sendActions(for: .touchUpInside)
    }
    
    //MARK: IBAction Outlets

    @IBAction func intentSampleAppBtn(_ sender: Any) {
        if let intentSampleAppVC = UIStoryboard(name: "Main", bundle: Bundle(for: IntentSampleAppViewController.self)).instantiateViewController(withIdentifier: String(describing: IntentSampleAppViewController.self)) as? IntentSampleAppViewController {
            intentSampleAppVC.handleDeepLinkParams(extSerialNo: extSerialNo, url: url, amount: amount)
            intentSampleAppVC.modalPresentationStyle = .overFullScreen
            self.navigationController?.pushViewController(intentSampleAppVC, animated: true)
        }
    }
    
    @IBAction func mstageQrAppBtn(_ sender: Any) {
        if let mscanDict = mscanDict {
            if let submitController = UIStoryboard(name: "Main", bundle: Bundle(for: SubmitPageViewController.self)).instantiateViewController(withIdentifier: String(describing: SubmitPageViewController.self)) as? SubmitPageViewController {
                let orderId = mscanDict["tr"]
                let amount = mscanDict["am"]
                let externalSerialNo = mscanDict["tid"]
                let split = mscanDict["split"]
                if let payeeVpa  = mscanDict["pa"], !payeeVpa.isEmpty {
                    submitController.updateLabels(amount: amount, orderId: orderId, payeeVpa: payeeVpa, externalSerialNo: externalSerialNo, split: split)
                }
                submitController.delegate = self
                submitController.tapSubmit = true
                submitController.modalPresentationStyle = .overCurrentContext
                navigationController?.pushViewController(submitController, animated: true)
            }
        } else if let qrScannerVC = UIStoryboard(name: "Main", bundle: Bundle(for: QRScannerViewController.self)).instantiateViewController(withIdentifier: String(describing: QRScannerViewController.self)) as? QRScannerViewController {
            qrScannerVC.modalPresentationStyle = .overCurrentContext
            self.navigationController?.pushViewController(qrScannerVC, animated: true)
        }
    }
    
    func resetDict() {
        self.mscanDict = nil
    }

}
